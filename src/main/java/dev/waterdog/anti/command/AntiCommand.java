package dev.waterdog.anti.command;

import dev.waterdog.anti.WaterdogAnti;
import dev.waterdog.waterdogpe.command.Command;
import dev.waterdog.waterdogpe.command.CommandSender;
import dev.waterdog.waterdogpe.command.CommandSettings;
import dev.waterdog.waterdogpe.player.ProxiedPlayer;

import java.util.ArrayList;
import java.util.List;

/**
 * Management command: {@code /wda <status|reload|info|unblock|whitelist|alerts|reset>}.
 */
public class AntiCommand extends Command {

    private final WaterdogAnti plugin;

    public AntiCommand(WaterdogAnti plugin) {
        super("waterdoganti", CommandSettings.builder()
                .setDescription("WaterdogAnti - anti-bot & anti-abuse protection")
                .setUsageMessage("/wda <status|reload|info|unblock|whitelist|alerts|reset>")
                .setPermission("waterdoganti.command")
                .setPermissionMessage("§cYou are not allowed to use this command!")
                .setAliases("wda", "anti")
                .build());
        this.plugin = plugin;
    }

    @Override
    public boolean onExecute(CommandSender sender, String alias, String[] args) {
        if (args.length == 0) {
            this.help(sender, alias);
            return true;
        }

        switch (args[0].toLowerCase()) {
            case "status" -> this.status(sender);
            case "reload" -> this.reload(sender);
            case "info" -> this.info(sender, args);
            case "unblock" -> this.unblock(sender, args);
            case "whitelist" -> this.whitelist(sender, args);
            case "alerts" -> this.alerts(sender);
            case "reset" -> this.reset(sender, args);
            default -> this.help(sender, alias);
        }
        return true;
    }

    private void help(CommandSender sender, String alias) {
        sender.sendMessage("§8§m----------------§r §cWaterdogAnti §8§m----------------");
        sender.sendMessage("§e/" + alias + " status §7- module status and counters");
        sender.sendMessage("§e/" + alias + " info <player> §7- show a player's identity & points");
        sender.sendMessage("§e/" + alias + " reset <player> §7- clear violation points");
        sender.sendMessage("§e/" + alias + " unblock <ip> §7- unblock an address");
        sender.sendMessage("§e/" + alias + " whitelist <add|remove|list> [name]");
        sender.sendMessage("§e/" + alias + " alerts §7- toggle staff alerts for yourself");
        sender.sendMessage("§e/" + alias + " reload §7- reload config.yml");
    }

    private void status(CommandSender sender) {
        sender.sendMessage("§8§m----------------§r §cWaterdogAnti§r §8§m----------------");
        sender.sendMessage("§7Version: §f" + this.plugin.getDescription().version);
        sender.sendMessage("§7Players on the proxy: §f" + this.plugin.getProxy().getPlayers().size());
        sender.sendMessage("§7Tracked packet stats: §f" + this.plugin.packetGuard().trackedPlayers());
        sender.sendMessage("§7Accepted joins: §a" + this.plugin.joinGuard().accepted()
                + " §7| Rejected: §c" + this.plugin.joinGuard().rejected());
        sender.sendMessage("§7Known player records: §f" + this.plugin.playerStore().size());
        sender.sendMessage("§7Your alert state: §f" + (this.plugin.violations().alertsEnabled(this.senderId(sender)) ? "ON" : "OFF"));
    }

    private void reload(CommandSender sender) {
        this.plugin.reload();
        sender.sendMessage("§aWaterdogAnti config reloaded. §7(numbers are live; enable/disable of modules needs a restart)");
    }

    private void info(CommandSender sender, String[] args) {
        if (args.length < 2) {
            sender.sendMessage("§cUsage: /wda info <player>");
            return;
        }
        ProxiedPlayer player = this.plugin.getProxy().getPlayer(args[1]);
        if (player == null) {
            sender.sendMessage("§cPlayer " + args[1] + " is not online (offline data lives in players.db).");
            return;
        }
        sender.sendMessage("§8§m----------------§r §c" + player.getName() + "§r §8§m----------------");
        sender.sendMessage("§7XUID: §f" + player.getXuid());
        sender.sendMessage("§7UUID: §f" + player.getUniqueId());
        sender.sendMessage("§7Address: §f" + player.getAddress());
        sender.sendMessage("§7Protocol: §f" + (player.getProtocol() == null ? "?" :
                player.getProtocol().getMinecraftVersion() + " (" + player.getProtocol().getProtocol() + ")"));
        sender.sendMessage("§7Device: §f" + player.getDevicePlatform() + " §7/ §f" + player.getDeviceModel());
        sender.sendMessage("§7Server: §f" + (player.getServerInfo() == null ? "-" : player.getServerInfo().getServerName()));
        sender.sendMessage("§7Violation points: §c" + this.plugin.violations().points(player.getUniqueId()));
        String known = this.plugin.playerStore().nameOf(player.getXuid());
        if (known != null && !known.equals(player.getName())) {
            sender.sendMessage("§7Previous name: §e" + known);
        }
    }

    private void unblock(CommandSender sender, String[] args) {
        if (args.length < 2) {
            sender.sendMessage("§cUsage: /wda unblock <ip>");
            return;
        }
        this.plugin.joinGuard().unblock(args[1]);
        sender.sendMessage("§aAddress " + args[1] + " unblocked.");
    }

    private void whitelist(CommandSender sender, String[] args) {
        if (args.length < 2) {
            sender.sendMessage("§cUsage: /wda whitelist <add|remove|list> [name]");
            return;
        }
        List<String> current = new ArrayList<>(this.plugin.settings().list("join-guard.whitelist"));
        switch (args[1].toLowerCase()) {
            case "add" -> {
                if (args.length < 3) {
                    sender.sendMessage("§cUsage: /wda whitelist add <name>");
                    return;
                }
                current.add(args[2]);
                this.saveWhitelist(current);
                sender.sendMessage("§a" + args[2] + " added to the whitelist (" + current.size() + " entries).");
            }
            case "remove" -> {
                if (args.length < 3) {
                    sender.sendMessage("§cUsage: /wda whitelist remove <name>");
                    return;
                }
                current.removeIf(entry -> entry.equalsIgnoreCase(args[2]));
                this.saveWhitelist(current);
                sender.sendMessage("§a" + args[2] + " removed from the whitelist.");
            }
            default -> sender.sendMessage("§7Whitelist (" + current.size() + "): §f" + String.join(", ", current));
        }
    }

    private void alerts(CommandSender sender) {
        if (!(sender instanceof ProxiedPlayer player)) {
            sender.sendMessage("§cOnly players can toggle alerts.");
            return;
        }
        this.plugin.violations().toggleAlerts(player.getUniqueId());
        sender.sendMessage("§aAlerts are now " + (this.plugin.violations().alertsEnabled(player.getUniqueId()) ? "§aON" : "§cOFF"));
    }

    private void reset(CommandSender sender, String[] args) {
        if (args.length < 2) {
            sender.sendMessage("§cUsage: /wda reset <player>");
            return;
        }
        ProxiedPlayer player = this.plugin.getProxy().getPlayer(args[1]);
        if (player == null) {
            sender.sendMessage("§cPlayer " + args[1] + " is not online.");
            return;
        }
        this.plugin.violations().reset(player.getUniqueId());
        sender.sendMessage("§aViolation points of " + player.getName() + " cleared.");
    }

    private void saveWhitelist(List<String> entries) {
        this.plugin.getConfig().setStringList("join-guard.whitelist", entries);
        this.plugin.getConfig().save();
        this.plugin.reload(); // pick the new list up in memory as well
    }

    private java.util.UUID senderId(CommandSender sender) {
        return sender instanceof ProxiedPlayer player ? player.getUniqueId() : new java.util.UUID(0L, 0L);
    }
}
