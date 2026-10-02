package dev.waterdog.anti;

import dev.waterdog.waterdogpe.utils.config.Configuration;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * Thin, null/exception safe wrapper around the proxy's {@link Configuration}.
 * <p>
 * WaterdogPE stores the plugin config as a plain (dotted key) map, so every accessor below
 * swallows malformed values and falls back to the default instead of breaking the proxy.
 */
public final class AntiConfig {

    private final Configuration config;

    public AntiConfig(Configuration config) {
        this.config = config;
    }

    public boolean bool(String key, boolean fallback) {
        try {
            Boolean value = this.config.getBoolean(key, fallback);
            return value != null ? value : fallback;
        } catch (Exception e) {
            return fallback;
        }
    }

    public int integer(String key, int fallback) {
        try {
            Integer value = this.config.getInt(key, fallback);
            return value != null ? value : fallback;
        } catch (Exception e) {
            return fallback;
        }
    }

    public String string(String key, String fallback) {
        Object value = this.config.get(key, fallback);
        return value == null ? fallback : String.valueOf(value);
    }

    /**
     * @return the list located at the key, converted to strings. Never null.
     */
    public List<String> list(String key) {
        Object value = this.config.get(key);
        List<String> result = new ArrayList<>();
        if (value instanceof List<?> raw) {
            for (Object element : raw) {
                if (element != null) {
                    result.add(String.valueOf(element));
                }
            }
        }
        return result;
    }

    /**
     * @return a map of the key's section mapped to integers, skipping anything that is not a number.
     */
    public Map<String, Integer> intMap(String key) {
        Object value = this.config.get(key);
        Map<String, Integer> result = new LinkedHashMap<>();
        if (value instanceof Map<?, ?> raw) {
            for (Map.Entry<?, ?> entry : raw.entrySet()) {
                if (entry.getKey() == null || entry.getValue() == null) {
                    continue;
                }
                try {
                    result.put(String.valueOf(entry.getKey()), Integer.parseInt(String.valueOf(entry.getValue()).trim()));
                } catch (NumberFormatException ignored) {
                    // malformed entry - skip it instead of killing the proxy
                }
            }
        }
        return result;
    }
}
