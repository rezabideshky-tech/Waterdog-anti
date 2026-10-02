package ir.arvangaming.app;

/**
 * Build-time fallback settings for the Arvan Gaming companion app.
 * Fill these only with values confirmed by the server owner. Public content may also come from
 * the HTTPS API contract described in README_FA.md.
 */
final class ArvanGamingConfig {
    // Leave blank until the real public Bedrock address is confirmed. Never ship a guessed host.
    static final String SERVER_HOST = "";
    static final int SERVER_PORT = 0;

    // Optional HTTPS API that serves real news/events/polls and secure account-linking endpoints.
    static final String API_BASE_URL = "";

    // Only list versions that the server owner has verified against the actual server/proxy.
    static final String[] SUPPORTED_CLIENT_VERSIONS = new String[0];

    static final String SUPPORT_URL = "";
    static final String TELEGRAM_URL = "";
    static final String DISCORD_URL = "";
    static final String INSTAGRAM_URL = "";

    private ArvanGamingConfig() {
    }

    static boolean hasServerAddress() {
        return SERVER_HOST != null && !SERVER_HOST.trim().isEmpty()
                && SERVER_PORT > 0 && SERVER_PORT <= 65535;
    }

    static boolean hasSecureApi() {
        return API_BASE_URL != null && API_BASE_URL.trim().startsWith("https://");
    }
}
