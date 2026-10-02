package ir.arvangaming.app;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/** Minimal HTTPS-only client for the optional Arvan Gaming companion API. */
final class ArvanApi {
    private static final int MAX_RESPONSE_BYTES = 1_500_000;

    private ArvanApi() {
    }

    static JSONObject get(String url, String bearerToken) throws IOException, JSONException {
        HttpURLConnection connection = open(url, "GET", bearerToken);
        try {
            return readJson(connection);
        } finally {
            connection.disconnect();
        }
    }

    static JSONObject post(String url, JSONObject body, String bearerToken) throws IOException, JSONException {
        HttpURLConnection connection = open(url, "POST", bearerToken);
        connection.setDoOutput(true);
        connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
        byte[] bytes = (body == null ? "{}" : body.toString()).getBytes(StandardCharsets.UTF_8);
        try (OutputStream output = connection.getOutputStream()) {
            output.write(bytes);
        }
        try {
            return readJson(connection);
        } finally {
            connection.disconnect();
        }
    }

    static String endpoint(String path) {
        String base = ArvanGamingConfig.API_BASE_URL == null
                ? "" : ArvanGamingConfig.API_BASE_URL.trim();
        while (base.endsWith("/")) {
            base = base.substring(0, base.length() - 1);
        }
        String suffix = path == null ? "" : path.trim();
        if (!suffix.startsWith("/")) {
            suffix = "/" + suffix;
        }
        return base + suffix;
    }

    private static HttpURLConnection open(String value, String method, String bearerToken) throws IOException {
        if (!ArvanGamingConfig.hasSecureApi()) {
            throw new IOException("The HTTPS API endpoint has not been configured");
        }
        URL url = new URL(value);
        if (!"https".equalsIgnoreCase(url.getProtocol())) {
            throw new IOException("The Arvan Gaming API must use HTTPS");
        }
        HttpURLConnection connection = (HttpURLConnection) url.openConnection();
        connection.setRequestMethod(method);
        connection.setConnectTimeout(6_000);
        connection.setReadTimeout(8_000);
        connection.setUseCaches(false);
        connection.setInstanceFollowRedirects(false);
        connection.setRequestProperty("Accept", "application/json");
        connection.setRequestProperty("User-Agent", "ArvanGaming-Android/1.0");
        if (bearerToken != null && !bearerToken.trim().isEmpty()) {
            connection.setRequestProperty("Authorization", "Bearer " + bearerToken.trim());
        }
        return connection;
    }

    private static JSONObject readJson(HttpURLConnection connection) throws IOException, JSONException {
        int status = connection.getResponseCode();
        InputStream input = status >= 200 && status < 300
                ? connection.getInputStream() : connection.getErrorStream();
        if (input == null) {
            throw new IOException("API returned HTTP " + status);
        }
        byte[] bytes;
        try (InputStream stream = input; ByteArrayOutputStream output = new ByteArrayOutputStream()) {
            byte[] buffer = new byte[8192];
            int read;
            int total = 0;
            while ((read = stream.read(buffer)) != -1) {
                total += read;
                if (total > MAX_RESPONSE_BYTES) {
                    throw new IOException("API response is too large");
                }
                output.write(buffer, 0, read);
            }
            bytes = output.toByteArray();
        }
        if (status < 200 || status >= 300) {
            throw new IOException("API returned HTTP " + status);
        }
        return new JSONObject(new String(bytes, StandardCharsets.UTF_8));
    }
}
