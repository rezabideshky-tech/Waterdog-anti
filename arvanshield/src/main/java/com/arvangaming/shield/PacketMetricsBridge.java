package com.arvangaming.shield;

import dev.waterdog.waterdogpe.network.NetworkMetrics;
import org.cloudburstmc.netty.channel.nethernet.config.NetherChannelMetrics;
import org.cloudburstmc.netty.channel.nethernet.config.NetherServerMetrics;
import org.cloudburstmc.netty.channel.raknet.config.RakChannelMetrics;
import org.cloudburstmc.netty.channel.raknet.config.RakServerMetrics;
import org.cloudburstmc.protocol.bedrock.PacketDirection;
import org.cloudburstmc.protocol.bedrock.data.PacketRecipient;

/**
 * Adds aggregate packet/traffic observations without replacing metrics provided by another plugin.
 * The WaterdogPE NetworkMetrics API reports counters; it cannot cancel individual player packets.
 */
final class PacketMetricsBridge implements NetworkMetrics {
    private final NetworkMetrics previous;
    private final TrafficWindow trafficWindow;

    PacketMetricsBridge(NetworkMetrics previous, TrafficWindow trafficWindow) {
        this.previous = previous;
        this.trafficWindow = trafficWindow;
    }

    NetworkMetrics previous() {
        return this.previous;
    }

    @Override
    public RakChannelMetrics rakMetrics(Leg leg) {
        return this.previous == null ? null : this.previous.rakMetrics(leg);
    }

    @Override
    public NetherChannelMetrics netherMetrics(Leg leg) {
        return this.previous == null ? null : this.previous.netherMetrics(leg);
    }

    @Override
    public RakServerMetrics rakServerMetrics() {
        return this.previous == null ? null : this.previous.rakServerMetrics();
    }

    @Override
    public NetherServerMetrics netherServerMetrics() {
        return this.previous == null ? null : this.previous.netherServerMetrics();
    }

    @Override
    public void packetQueueTooLarge() {
        this.trafficWindow.recordOversizedPacketQueue();
        if (this.previous != null) {
            this.previous.packetQueueTooLarge();
        }
    }

    @Override
    public void compressedBytes(int count, PacketDirection direction) {
        this.trafficWindow.recordCompressedBytes(count);
        if (this.previous != null) {
            this.previous.compressedBytes(count, direction);
        }
    }

    @Override
    public void decompressedBytes(int count, PacketDirection direction) {
        // WaterdogPE marks client-originated batches with inbound recipient SERVER.
        if (isClientToProxy(direction)) {
            this.trafficWindow.recordInboundDecodedBytes(count);
        }
        if (this.previous != null) {
            this.previous.decompressedBytes(count, direction);
        }
    }

    @Override
    public void passedThroughBytes(int count, PacketDirection direction) {
        if (this.previous != null) {
            this.previous.passedThroughBytes(count, direction);
        }
    }

    @Override
    public void passedThroughPackets(int count, PacketDirection direction) {
        if (isClientToProxy(direction)) {
            this.trafficWindow.recordPassedThroughPackets(count);
        }
        if (this.previous != null) {
            this.previous.passedThroughPackets(count, direction);
        }
    }

    @Override
    public void encodedPackets(int count, PacketDirection direction) {
        if (this.previous != null) {
            this.previous.encodedPackets(count, direction);
        }
    }

    @Override
    public void droppedBytes(int count) {
        this.trafficWindow.recordDroppedBytes(count);
        if (this.previous != null) {
            this.previous.droppedBytes(count);
        }
    }

    private static boolean isClientToProxy(PacketDirection direction) {
        return direction != null && direction.getInbound() == PacketRecipient.SERVER;
    }
}
