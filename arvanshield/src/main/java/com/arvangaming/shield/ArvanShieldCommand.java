package com.arvangaming.shield;

import dev.waterdog.waterdogpe.command.Command;
import dev.waterdog.waterdogpe.command.CommandSender;
import dev.waterdog.waterdogpe.command.CommandSettings;

import java.util.Locale;

final class ArvanShieldCommand extends Command {
    private static final String USAGE = "/arvanshield [status|mode <off|observe|balanced|attack>|reload]";
    private final ArvanShieldPlugin plugin;

    ArvanShieldCommand(ArvanShieldPlugin plugin) {
        super("arvanshield", CommandSettings.builder()
                .setDescription("ArvanShield security controls")
                .setUsageMessage(USAGE)
                .setPermission("arvanshield.admin")
                .setPermissionMessage("§c§l[ArvanShield] §r§cYou do not have permission to use this command.")
                .setAliases("as")
                .build());
        this.plugin = plugin;
    }

    @Override
    public boolean onExecute(CommandSender sender, String alias, String[] args) {
        if (args.length == 0 || args[0].equalsIgnoreCase("status")) {
            sendStatus(sender);
            return true;
        }

        switch (args[0].toLowerCase(Locale.ROOT)) {
            case "mode" -> {
                if (args.length < 2) {
                    sender.sendMessage("§e§l[ArvanShield] §r§eModes: §foff, observe, balanced, attack§e. " + USAGE);
                    return true;
                }
                AntiBotConfig.Mode mode = parseMode(args[1]);
                if (mode == null) {
                    sender.sendMessage("§c§l[ArvanShield] §r§cUnknown mode. Use: off, observe, balanced, or attack.");
                    return true;
                }
                this.plugin.getEngine().setMode(mode);
                sender.sendMessage("§a§l[ArvanShield] §r§aMode changed to §f" + mode.configName()
                        + "§a for this run. Edit config.yml to keep it after restart.");
                if (mode == AntiBotConfig.Mode.ATTACK) {
                    sender.sendMessage("§e[ArvanShield] Attack mode may restrict new logins; existing players stay connected.");
                }
                return true;
            }
            case "reload" -> {
                this.plugin.reloadShieldConfiguration();
                sender.sendMessage("§a§l[ArvanShield] §r§aConfiguration reloaded. Mode: §f"
                        + this.plugin.getEngine().getMode().configName());
                return true;
            }
            case "help" -> {
                sender.sendMessage("§6§l[ArvanShield] §r§6Security controls");
                sender.sendMessage("§e/arvanshield status §7- Show login and aggregate network statistics");
                sender.sendMessage("§e/arvanshield mode <off|observe|balanced|attack> §7- Change mode until restart");
                sender.sendMessage("§e/arvanshield reload §7- Reload config.yml");
                return true;
            }
            default -> {
                sender.sendMessage("§e§l[ArvanShield] §r§eUsage: " + USAGE);
                return true;
            }
        }
    }

    private void sendStatus(CommandSender sender) {
        AntiBotEngine.Snapshot status = this.plugin.getEngine().snapshot();
        TrafficWindow.Snapshot traffic = status.traffic();
        sender.sendMessage("§6§lARVANSHIELD §8| §7Mode: §f" + status.mode().configName());
        sender.sendMessage("§7Logins: §f" + status.attempts()
                + " §8| §aAllowed: §f" + status.allowed()
                + " §8| §cRejected: §f" + status.rejected()
                + " §8| §eWould reject: §f" + status.wouldReject());
        sender.sendMessage("§bTraffic monitor: "
                + (this.plugin.isNetworkMetricsAttached() ? "§aattached" : "§cnot attached")
                + " §8| §7Inbound decoded: §f" + formatRate(traffic.inboundDecodedBytesPerSecond())
                + " §8| §7Passed packets: §f" + traffic.passedThroughPacketsPerSecond() + "/s");
        sender.sendMessage("§7Compressed: §f" + formatRate(traffic.compressedBytesPerSecond())
                + " §8| §7Dropped datagram bytes: §f" + formatRate(traffic.droppedBytesPerSecond())
                + " §8| §7Oversized transfer queues: §f" + traffic.oversizedPacketQueues());
        sender.sendMessage("§7Sustained high-load windows: §f" + traffic.consecutiveHighWindows()
                + " §8| §7New logins shed: §c" + status.pressureRejected()
                + " §8| §7Last risk score: §f" + status.lastRiskScore()
                + " §8| §7Tracked keys: §f" + status.trackedBuckets());
        if (traffic.highPressure()) {
            sender.sendMessage("§c§l[ArvanShield] §r§cSustained traffic pressure detected. New-login shedding works only in attack mode when enabled.");
        }
        sender.sendMessage("§8Packet counters are aggregate observations, not per-player packet blocking.");
    }

    private static String formatRate(long bytesPerSecond) {
        if (bytesPerSecond >= 1_048_576L) {
            return String.format(Locale.ROOT, "%.2f MiB/s", bytesPerSecond / 1_048_576.0);
        }
        if (bytesPerSecond >= 1_024L) {
            return String.format(Locale.ROOT, "%.1f KiB/s", bytesPerSecond / 1_024.0);
        }
        return bytesPerSecond + " B/s";
    }

    private static AntiBotConfig.Mode parseMode(String value) {
        return switch (value.toLowerCase(Locale.ROOT)) {
            case "off" -> AntiBotConfig.Mode.OFF;
            case "observe" -> AntiBotConfig.Mode.OBSERVE;
            case "balanced" -> AntiBotConfig.Mode.BALANCED;
            case "attack" -> AntiBotConfig.Mode.ATTACK;
            default -> null;
        };
    }
}
