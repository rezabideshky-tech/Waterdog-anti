package com.example.wdlf;

import org.yaml.snakeyaml.Yaml;

import java.io.File;
import java.io.InputStream;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

public class FallbackConfig {

    public List<String> lobbyServers = new ArrayList<>(List.of("lobby-1", "lobby-2"));
    public String transferMessage = "§eThe server you were on went offline - reconnecting you to the lobby...";
    public String noLobbyMessage = "§cServer went offline and no lobby is currently available. Please reconnect shortly.";
    public boolean ignoreIfAlreadyOnLobby = true;
    public int transferDelayTicks = 10;

    @SuppressWarnings("unchecked")
    public static FallbackConfig load(WaterdogLobbyFallback plugin) {
        FallbackConfig cfg = new FallbackConfig();
        try {
            File dataFolder = plugin.getDataFolder();
            if (!dataFolder.exists()) dataFolder.mkdirs();
            File file = new File(dataFolder, "config.yml");

            if (!file.exists()) {
                try (InputStream in = plugin.getClass().getClassLoader()
                        .getResourceAsStream("config.yml")) {
                    if (in != null) {
                        java.nio.file.Files.copy(in, file.toPath());
                    } else {
                        file.createNewFile();
                    }
                }
            }

            Yaml yaml = new Yaml();
            try (InputStream in = java.nio.file.Files.newInputStream(file.toPath())) {
                Map<String, Object> data = yaml.load(in);
                if (data != null) {
                    Object lobbies = data.get("lobby-servers");
                    if (lobbies instanceof List<?> list && !list.isEmpty()) {
                        cfg.lobbyServers = new ArrayList<>();
                        for (Object o : list) cfg.lobbyServers.add(String.valueOf(o));
                    }
                    Object tm = data.get("transfer-message");
                    if (tm != null) cfg.transferMessage = tm.toString();
                    Object nlm = data.get("no-lobby-message");
                    if (nlm != null) cfg.noLobbyMessage = nlm.toString();
                    Object ignore = data.get("ignore-if-already-on-lobby");
                    if (ignore != null) cfg.ignoreIfAlreadyOnLobby = (Boolean) ignore;
                    Object delay = data.get("transfer-delay-ticks");
                    if (delay != null) cfg.transferDelayTicks = ((Number) delay).intValue();
                }
            }
        } catch (Exception e) {
            plugin.getLogger().error("Failed to load config.yml, using defaults.", e);
        }
        return cfg;
    }
}
