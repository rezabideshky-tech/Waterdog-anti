package ir.arvangaming.app;

import java.io.IOException;
import java.net.DatagramPacket;
import java.net.DatagramSocket;
import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.nio.charset.StandardCharsets;
import java.util.Arrays;

/** Queries a Bedrock/RakNet server-list pong. No player count is invented on timeout or bad data. */
final class BedrockPingClient {
    private static final byte[] RAKNET_MAGIC = new byte[]{
            0x00, (byte) 0xff, (byte) 0xff, 0x00,
            (byte) 0xfe, (byte) 0xfe, (byte) 0xfe, (byte) 0xfe,
            (byte) 0xfd, (byte) 0xfd, (byte) 0xfd,
            0x12, 0x34, 0x56, 0x78
    };

    private BedrockPingClient() {
    }

    static Result ping(String host, int port, int timeoutMillis) throws IOException {
        if (host == null || host.trim().isEmpty() || port < 1 || port > 65535) {
            throw new IllegalArgumentException("Bedrock host and port are not configured");
        }

        InetSocketAddress destination = new InetSocketAddress(host.trim(), port);
        if (destination.isUnresolved()) {
            throw new IOException("Server address could not be resolved");
        }

        long sentAt = System.currentTimeMillis();
        long clientGuid = System.nanoTime() ^ sentAt;
        ByteBuffer request = ByteBuffer.allocate(1 + 8 + RAKNET_MAGIC.length + 8);
        request.order(ByteOrder.BIG_ENDIAN);
        request.put((byte) 0x01); // Unconnected Ping
        request.putLong(sentAt);
        request.put(RAKNET_MAGIC);
        request.putLong(clientGuid);

        byte[] requestBytes = request.array();
        byte[] responseBytes = new byte[2048];
        try (DatagramSocket socket = new DatagramSocket()) {
            socket.setSoTimeout(Math.max(300, timeoutMillis));
            socket.send(new DatagramPacket(requestBytes, requestBytes.length, destination));
            DatagramPacket response = new DatagramPacket(responseBytes, responseBytes.length);
            socket.receive(response);

            if (!destination.getAddress().equals(response.getAddress()) || response.getPort() != port) {
                throw new IOException("Received a response from an unexpected address");
            }
            return parse(response.getData(), response.getLength());
        }
    }

    private static Result parse(byte[] data, int length) throws IOException {
        // 0x1c + ping time + server GUID + RakNet magic + string length.
        final int stringLengthOffset = 33;
        if (length < stringLengthOffset + 2 || (data[0] & 0xff) != 0x1c) {
            throw new IOException("Not a Bedrock/RakNet pong");
        }
        byte[] receivedMagic = Arrays.copyOfRange(data, 17, 17 + RAKNET_MAGIC.length);
        if (!Arrays.equals(RAKNET_MAGIC, receivedMagic)) {
            throw new IOException("Invalid RakNet pong signature");
        }

        int textLength = ((data[stringLengthOffset] & 0xff) << 8)
                | (data[stringLengthOffset + 1] & 0xff);
        int textOffset = stringLengthOffset + 2;
        if (textLength < 0 || textOffset + textLength > length) {
            throw new IOException("Malformed Bedrock pong data");
        }

        String motd = new String(data, textOffset, textLength, StandardCharsets.UTF_8);
        String[] fields = motd.split(";", -1);
        String serverVersion = fields.length > 3 ? fields[3].trim() : "";
        int online = fields.length > 4 ? parseNonNegative(fields[4]) : -1;
        int maximum = fields.length > 5 ? parseNonNegative(fields[5]) : -1;
        String message = fields.length > 1 ? fields[1].trim() : motd;
        return new Result(true, online, maximum, serverVersion, message);
    }

    private static int parseNonNegative(String value) {
        try {
            int parsed = Integer.parseInt(value.trim());
            return parsed >= 0 ? parsed : -1;
        } catch (RuntimeException ignored) {
            return -1;
        }
    }

    static final class Result {
        final boolean responded;
        final int onlinePlayers;
        final int maximumPlayers;
        final String serverVersion;
        final String motd;

        Result(boolean responded, int onlinePlayers, int maximumPlayers, String serverVersion, String motd) {
            this.responded = responded;
            this.onlinePlayers = onlinePlayers;
            this.maximumPlayers = maximumPlayers;
            this.serverVersion = serverVersion == null ? "" : serverVersion;
            this.motd = motd == null ? "" : motd;
        }
    }
}
