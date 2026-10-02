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
                .setDescription("مدیریت آنتی‌بات ArvanShield")
                .setUsageMessage(USAGE)
                .setPermission("arvanshield.admin")
                .setPermissionMessage("§cاجازهٔ استفاده از این دستور را ندارید.")
                .setAliases("as")
                .build());
        this.plugin = plugin;
    }

    @Override
    public boolean onExecute(CommandSender sender, String alias, String[] args) {
        if (args.length == 0 || args[0].equalsIgnoreCase("status")) {
            AntiBotEngine.Snapshot status = this.plugin.getEngine().snapshot();
            sender.sendMessage("§6ArvanShield §7| حالت: §f" + status.mode().configName());
            sender.sendMessage("§7ورودها: §f" + status.attempts()
                    + " §7| مجاز: §a" + status.allowed()
                    + " §7| ردشده: §c" + status.rejected());
            sender.sendMessage("§7رد احتمالی در حالت observe: §e" + status.wouldReject()
                    + " §7| آخرین امتیاز ریسک: §f" + status.lastRiskScore()
                    + " §7| باکت‌های فعال: §f" + status.trackedBuckets());
            return true;
        }

        switch (args[0].toLowerCase(Locale.ROOT)) {
            case "mode" -> {
                if (args.length < 2) {
                    sender.sendMessage("§eحالت‌ها: off, observe, balanced, attack. §7" + USAGE);
                    return true;
                }
                AntiBotConfig.Mode mode = parseMode(args[1]);
                if (mode == null) {
                    sender.sendMessage("§cحالت نامعتبر است. حالت‌های مجاز: off, observe, balanced, attack.");
                    return true;
                }
                this.plugin.getEngine().setMode(mode);
                sender.sendMessage("§aحالت ArvanShield برای این اجرا روی §f" + mode.configName()
                        + "§a تنظیم شد. برای ماندگاری، mode را در config.yml هم تغییر دهید.");
                return true;
            }
            case "reload" -> {
                this.plugin.reloadShieldConfiguration();
                sender.sendMessage("§aتنظیمات ArvanShield بارگذاری شد.");
                return true;
            }
            case "help" -> {
                sender.sendMessage("§6/arvanshield status §7- نمایش آمار و حالت فعلی");
                sender.sendMessage("§6/arvanshield mode <off|observe|balanced|attack> §7- تغییر موقت حالت");
                sender.sendMessage("§6/arvanshield reload §7- بارگذاری دوبارهٔ config.yml");
                return true;
            }
            default -> {
                sender.sendMessage("§e" + USAGE);
                return true;
            }
        }
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
