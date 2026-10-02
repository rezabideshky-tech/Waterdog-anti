package com.arvangaming.wdlf;

import dev.waterdog.waterdogpe.utils.config.Configuration;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collections;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;

/** Immutable, validated settings for the lobby reconnect handler. */
final class FallbackConfig {
    private static final List<String> DEFAULT_LOBBIES = Collections.singletonList("lobby");
    private static final List<String> DEFAULT_REASONS = Arrays.asList(
            "timeout", "exception", "unknown", "server_kick", "incompatible", "transfer_failed");

    final List<String> lobbyServers;
    final Set<String> fallbackReasons;
    final String transferMessage;
    final String noLobbyMessage;
    final String currentLobbyMessage;
    final boolean fallbackOnTransferFailure;
    final boolean keepCurrentLobbyOnTransferFailure;

    private FallbackConfig(List<String> lobbyServers,
                           Set<String> fallbackReasons,
                           String transferMessage,
                           String noLobbyMessage,
                           String currentLobbyMessage,
                           boolean fallbackOnTransferFailure,
                           boolean keepCurrentLobbyOnTransferFailure) {
        this.lobbyServers = lobbyServers;
        this.fallbackReasons = fallbackReasons;
        this.transferMessage = transferMessage;
        this.noLobbyMessage = noLobbyMessage;
        this.currentLobbyMessage = currentLobbyMessage;
        this.fallbackOnTransferFailure = fallbackOnTransferFailure;
        this.keepCurrentLobbyOnTransferFailure = keepCurrentLobbyOnTransferFailure;
    }

    static FallbackConfig load(Configuration source) {
        List<String> lobbyServers = cleanNames(source.getStringList("lobby-servers", DEFAULT_LOBBIES));
        Set<String> reasons = cleanReasons(source.getStringList("fallback-reasons", DEFAULT_REASONS));

        return new FallbackConfig(
                lobbyServers,
                reasons,
                source.getString("transfer-message", "§eسرور موقتاً از دسترس خارج شد؛ در حال انتقال به لابی..."),
                source.getString("no-lobby-message", "§cهیچ لابی در دسترس نیست. لطفاً کمی بعد دوباره تلاش کن."),
                source.getString("current-lobby-message", "§cسرور مقصد در دسترس نیست؛ در لابی می‌مانی."),
                source.getBoolean("fallback-on-transfer-failure", true),
                source.getBoolean("keep-current-lobby-on-transfer-failure", true)
        );
    }

    boolean shouldHandle(String reason) {
        return reason == null || this.fallbackReasons.contains(normalize(reason));
    }

    boolean isLobby(String serverName) {
        if (serverName == null) return false;
        String normalized = normalize(serverName);
        for (String lobby : this.lobbyServers) {
            if (normalized.equals(normalize(lobby))) return true;
        }
        return false;
    }

    private static List<String> cleanNames(List<String> input) {
        if (input == null || input.isEmpty()) return Collections.emptyList();
        LinkedHashSet<String> unique = new LinkedHashSet<String>();
        for (String value : input) {
            if (value == null) continue;
            String name = value.trim();
            if (!name.isEmpty()) unique.add(name);
        }
        return Collections.unmodifiableList(new ArrayList<String>(unique));
    }

    private static Set<String> cleanReasons(List<String> input) {
        if (input == null || input.isEmpty()) return Collections.emptySet();
        LinkedHashSet<String> reasons = new LinkedHashSet<String>();
        for (String value : input) {
            if (value != null && !value.trim().isEmpty()) reasons.add(normalize(value));
        }
        return Collections.unmodifiableSet(reasons);
    }

    private static String normalize(String value) {
        return value.trim().toLowerCase(Locale.ROOT);
    }
}
