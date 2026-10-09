-- #! mysql

-- # { bedwars

-- #   { init
CREATE TABLE IF NOT EXISTS bw_players (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(36)  UNIQUE NOT NULL,
    coins               INT          DEFAULT 0,
    kills               INT          DEFAULT 0,
    wins                INT          DEFAULT 0,
    beds_broken         INT          DEFAULT 0,
    final_kills         INT          DEFAULT 0,
    deaths              INT          DEFAULT 0,
    xp                  INT          DEFAULT 0,
    level               INT          DEFAULT 1,
    win_streak          INT          DEFAULT 0,
    best_win_streak     INT          DEFAULT 0,
    kill_effect         VARCHAR(64)  DEFAULT 'none',
    kill_sound          VARCHAR(64)  DEFAULT 'none',
    kill_message        VARCHAR(64)  DEFAULT 'none',
    cape                VARCHAR(64)  DEFAULT 'none',
    wing                VARCHAR(64)  DEFAULT 'none',
    hat                 VARCHAR(64)  DEFAULT 'none',
    particle            VARCHAR(64)  DEFAULT 'none',
    dance               VARCHAR(64)  DEFAULT 'none',
    unlocked_cosmetics  TEXT         DEFAULT '[]',
    wearable_pet        VARCHAR(64)  DEFAULT 'none',
    wearable_wing       VARCHAR(64)  DEFAULT 'none',
    wearable_cape       VARCHAR(64)  DEFAULT 'none',
    wearable_hat        VARCHAR(64)  DEFAULT 'none',
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #   }

-- #   { migrate_deaths
ALTER TABLE bw_players ADD COLUMN IF NOT EXISTS deaths INT DEFAULT 0;
-- #   }

-- #   { migrate_rejoin_mode
ALTER TABLE bw_rejoin ADD COLUMN IF NOT EXISTS mode VARCHAR(16) NOT NULL DEFAULT 'Solo';
-- #   }

-- #   { migrate_rejoin_confirmed
-- Tracks whether the player explicitly asked to come back (right-clicked
-- the Rejoin Ticket on the lobby) rather than just having a still-valid
-- record sitting in the table. Without this, a player who ignores the
-- ticket and queues into a fresh match through normal matchmaking could
-- land back on the same game server that still has their old match
-- running and get silently yanked back into it - JoinListener now only
-- auto-rejoins when this flag is set.
ALTER TABLE bw_rejoin ADD COLUMN IF NOT EXISTS confirmed TINYINT(1) NOT NULL DEFAULT 0;
-- #   }

-- #   { migrate_win_streak
ALTER TABLE bw_players ADD COLUMN IF NOT EXISTS win_streak INT DEFAULT 0;
-- #   }

-- #   { migrate_best_win_streak
ALTER TABLE bw_players ADD COLUMN IF NOT EXISTS best_win_streak INT DEFAULT 0;
-- #   }

-- #   { migrate_dance
ALTER TABLE bw_players ADD COLUMN IF NOT EXISTS dance VARCHAR(64) DEFAULT 'none';
-- #   }

-- #   { migrate_wearable_pet
ALTER TABLE bw_players ADD COLUMN IF NOT EXISTS wearable_pet VARCHAR(64) DEFAULT 'none';
-- #   }

-- #   { migrate_wearable_wing
ALTER TABLE bw_players ADD COLUMN IF NOT EXISTS wearable_wing VARCHAR(64) DEFAULT 'none';
-- #   }

-- #   { migrate_wearable_cape
ALTER TABLE bw_players ADD COLUMN IF NOT EXISTS wearable_cape VARCHAR(64) DEFAULT 'none';
-- #   }

-- #   { migrate_wearable_hat
ALTER TABLE bw_players ADD COLUMN IF NOT EXISTS wearable_hat VARCHAR(64) DEFAULT 'none';
-- #   }

-- #   { migrate_kill_message
ALTER TABLE bw_players ADD COLUMN IF NOT EXISTS kill_message VARCHAR(64) DEFAULT 'none';
-- #   }

-- #   { migrate_level_default
-- New players start at level 1. bw_players.level is created with DEFAULT 1,
-- and CREATE TABLE IF NOT EXISTS never touches that default on a database
-- that already has the table - this ALTER fixes it for existing installs.
ALTER TABLE bw_players ALTER level SET DEFAULT 1;
-- #   }

-- #   { migrate_level_min
-- Anyone still sitting at level 0 (created while the old default was 0)
-- is moved up to level 1 so the whole player base starts from level 1.
UPDATE bw_players SET level = 1 WHERE level < 1;
-- #   }

-- #   { player

-- #     { load
-- #       : username string
SELECT coins, kills, wins, beds_broken, final_kills, deaths, xp, level,
       win_streak, best_win_streak,
       kill_effect, kill_sound, kill_message, cape, wing, hat, particle, dance, unlocked_cosmetics,
       wearable_pet, wearable_wing, wearable_cape, wearable_hat
FROM bw_players WHERE username = :username LIMIT 1;
-- #     }

-- #     { get_coins
-- #       : username string
SELECT coins FROM bw_players WHERE username = :username LIMIT 1;
-- #     }

-- #     { deduct_coins
-- #       : username string
-- #       : amount int
UPDATE bw_players SET coins = coins - :amount WHERE username = :username AND coins >= :amount;
-- #     }

-- #     { save
-- #       : username string
-- #       : coins int
-- #       : kills int
-- #       : wins int
-- #       : beds_broken int
-- #       : final_kills int
-- #       : deaths int
-- #       : xp int
-- #       : level int
-- #       : win_streak int
-- #       : best_win_streak int
-- #       : kill_effect string
-- #       : kill_sound string
-- #       : kill_message string
-- #       : cape string
-- #       : wing string
-- #       : hat string
-- #       : particle string
-- #       : dance string
-- #       : unlocked_cosmetics string
-- #       : wearable_pet string
-- #       : wearable_wing string
-- #       : wearable_cape string
-- #       : wearable_hat string
INSERT INTO bw_players
    (username, coins, kills, wins, beds_broken, final_kills, deaths, xp, level,
     win_streak, best_win_streak,
     kill_effect, kill_sound, kill_message, cape, wing, hat, particle, dance, unlocked_cosmetics,
     wearable_pet, wearable_wing, wearable_cape, wearable_hat)
VALUES
    (:username, :coins, :kills, :wins, :beds_broken, :final_kills, :deaths, :xp, :level,
     :win_streak, :best_win_streak,
     :kill_effect, :kill_sound, :kill_message, :cape, :wing, :hat, :particle, :dance, :unlocked_cosmetics,
     :wearable_pet, :wearable_wing, :wearable_cape, :wearable_hat)
ON DUPLICATE KEY UPDATE
    coins=VALUES(coins), kills=VALUES(kills), wins=VALUES(wins),
    beds_broken=VALUES(beds_broken), final_kills=VALUES(final_kills),
    deaths=VALUES(deaths),
    xp=VALUES(xp), level=VALUES(level),
    win_streak=VALUES(win_streak), best_win_streak=VALUES(best_win_streak),
    kill_effect=VALUES(kill_effect), kill_sound=VALUES(kill_sound), kill_message=VALUES(kill_message),
    cape=VALUES(cape), wing=VALUES(wing), hat=VALUES(hat),
    particle=VALUES(particle), dance=VALUES(dance), unlocked_cosmetics=VALUES(unlocked_cosmetics),
    wearable_pet=VALUES(wearable_pet), wearable_wing=VALUES(wearable_wing),
    wearable_cape=VALUES(wearable_cape), wearable_hat=VALUES(wearable_hat);
-- #     }

-- #     { create
-- #       : username string
INSERT IGNORE INTO bw_players (username) VALUES (:username);
-- #     }

-- #   }

-- #   { leaderboard

-- #     { kills
-- #       : limit int
SELECT username, kills FROM bw_players ORDER BY kills DESC LIMIT :limit;
-- #     }

-- #     { wins
-- #       : limit int
SELECT username, wins FROM bw_players ORDER BY wins DESC LIMIT :limit;
-- #     }

-- #     { beds_broken
-- #       : limit int
SELECT username, beds_broken FROM bw_players ORDER BY beds_broken DESC LIMIT :limit;
-- #     }

-- #     { final_kills
-- #       : limit int
SELECT username, final_kills FROM bw_players ORDER BY final_kills DESC LIMIT :limit;
-- #     }

-- #     { deaths
-- #       : limit int
SELECT username, deaths FROM bw_players ORDER BY deaths DESC LIMIT :limit;
-- #     }

-- #     { level
-- #       : limit int
SELECT username, level FROM bw_players ORDER BY level DESC LIMIT :limit;
-- #     }

-- #     { coins
-- #       : limit int
SELECT username, coins FROM bw_players ORDER BY coins DESC LIMIT :limit;
-- #     }

-- #     { win_streak
-- #       : limit int
SELECT username, best_win_streak AS win_streak FROM bw_players ORDER BY best_win_streak DESC LIMIT :limit;
-- #     }

-- #   }

-- #   { party_follow

-- #     { create_table
CREATE TABLE IF NOT EXISTS bw_party_follow (
    username        VARCHAR(36) PRIMARY KEY,
    leader          VARCHAR(36) NOT NULL,
    target_server   VARCHAR(64) NOT NULL,
    created_at      INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { set
-- #       : username string
-- #       : leader string
-- #       : target_server string
-- #       : created_at int
INSERT INTO bw_party_follow (username, leader, target_server, created_at)
VALUES (:username, :leader, :target_server, :created_at)
ON DUPLICATE KEY UPDATE
    leader = VALUES(leader),
    target_server = VALUES(target_server),
    created_at = VALUES(created_at);
-- #     }

-- #     { get
-- #       : username string
SELECT leader, target_server, created_at
FROM bw_party_follow
WHERE username = :username
LIMIT 1;
-- #     }

-- #     { clear
-- #       : username string
DELETE FROM bw_party_follow
WHERE username = :username;
-- #     }

-- #   }

-- #   { rejoin

-- #     { create_table
CREATE TABLE IF NOT EXISTS bw_rejoin (
    username        VARCHAR(36) PRIMARY KEY,
    target_server   VARCHAR(64) NOT NULL,
    game_id         INT NOT NULL,
    team            VARCHAR(32) NOT NULL,
    mode            VARCHAR(16) NOT NULL DEFAULT 'Solo',
    confirmed       TINYINT(1) NOT NULL DEFAULT 0,
    created_at      INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { set
-- #       : username string
-- #       : target_server string
-- #       : game_id int
-- #       : team string
-- #       : mode string
-- #       : created_at int
INSERT INTO bw_rejoin (username, target_server, game_id, team, mode, confirmed, created_at)
VALUES (:username, :target_server, :game_id, :team, :mode, 0, :created_at)
ON DUPLICATE KEY UPDATE
    target_server = VALUES(target_server),
    game_id = VALUES(game_id),
    team = VALUES(team),
    mode = VALUES(mode),
    confirmed = 0,
    created_at = VALUES(created_at);
-- #     }

-- #     { confirm
-- #       : username string
UPDATE bw_rejoin
SET confirmed = 1
WHERE username = :username;
-- #     }

-- #     { get
-- #       : username string
SELECT target_server, game_id, team, mode, confirmed, created_at
FROM bw_rejoin
WHERE username = :username
LIMIT 1;
-- #     }

-- #     { clear
-- #       : username string
DELETE FROM bw_rejoin
WHERE username = :username;
-- #     }

-- #   }

-- #   { quest

-- #     { create_table
CREATE TABLE IF NOT EXISTS bw_quests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(64)  NOT NULL,
    description     VARCHAR(255) DEFAULT '',
    requirements    TEXT         NOT NULL,
    reward_coins    INT          DEFAULT 0,
    reward_xp       INT          DEFAULT 0,
    enabled         TINYINT(1)   DEFAULT 1,
    created_at      INT          NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { create_progress_table
CREATE TABLE IF NOT EXISTS bw_quest_progress (
    username    VARCHAR(36) NOT NULL,
    quest_id    INT         NOT NULL,
    progress    TEXT        NOT NULL,
    completed   TINYINT(1)  DEFAULT 0,
    PRIMARY KEY (username, quest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { add
-- #       : name string
-- #       : description string
-- #       : requirements string
-- #       : reward_coins int
-- #       : reward_xp int
-- #       : created_at int
INSERT INTO bw_quests (name, description, requirements, reward_coins, reward_xp, enabled, created_at)
VALUES (:name, :description, :requirements, :reward_coins, :reward_xp, 1, :created_at);
-- #     }

-- #     { remove
-- #       : id int
DELETE FROM bw_quests WHERE id = :id;
-- #     }

-- #     { set_enabled
-- #       : id int
-- #       : enabled int
UPDATE bw_quests SET enabled = :enabled WHERE id = :id;
-- #     }

-- #     { get_all
SELECT id, name, description, requirements, reward_coins, reward_xp, enabled FROM bw_quests;
-- #     }

-- #     { get_enabled
SELECT id, name, description, requirements, reward_coins, reward_xp, enabled FROM bw_quests WHERE enabled = 1;
-- #     }

-- #     { get_progress
-- #       : username string
SELECT quest_id, progress, completed FROM bw_quest_progress WHERE username = :username;
-- #     }

-- #     { set_progress
-- #       : username string
-- #       : quest_id int
-- #       : progress string
-- #       : completed int
INSERT INTO bw_quest_progress (username, quest_id, progress, completed)
VALUES (:username, :quest_id, :progress, :completed)
ON DUPLICATE KEY UPDATE progress = VALUES(progress), completed = VALUES(completed);
-- #     }

-- #     { reset_progress_for_quest
-- #       : quest_id int
DELETE FROM bw_quest_progress WHERE quest_id = :quest_id;
-- #     }

-- #   }

-- #   { quest_v2

-- #     { create_table
CREATE TABLE IF NOT EXISTS bw_quest_state (
    username    VARCHAR(36) PRIMARY KEY,
    state       MEDIUMTEXT NOT NULL,
    updated_at  INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { get
-- #       : username string
SELECT state
FROM bw_quest_state
WHERE username = :username
LIMIT 1;
-- #     }

-- #     { set
-- #       : username string
-- #       : state string
-- #       : updated int
INSERT INTO bw_quest_state (username, state, updated_at)
VALUES (:username, :state, :updated)
ON DUPLICATE KEY UPDATE
    state = VALUES(state),
    updated_at = VALUES(updated_at);
-- #     }

-- #   }

-- #   { reward

-- #     { create_table
CREATE TABLE IF NOT EXISTS bw_reward_state (
    username    VARCHAR(36) PRIMARY KEY,
    state       MEDIUMTEXT NOT NULL,
    updated_at  INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { get
-- #       : username string
SELECT state
FROM bw_reward_state
WHERE username = :username
LIMIT 1;
-- #     }

-- #     { set
-- #       : username string
-- #       : state string
-- #       : updated int
INSERT INTO bw_reward_state (username, state, updated_at)
VALUES (:username, :state, :updated)
ON DUPLICATE KEY UPDATE
    state = VALUES(state),
    updated_at = VALUES(updated_at);
-- #     }

-- #   }

-- #   { shop_layout

-- #     { create_table
CREATE TABLE IF NOT EXISTS bw_shop_layout (
    username    VARCHAR(36) PRIMARY KEY,
    layout      MEDIUMTEXT NOT NULL,
    updated_at  INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { get
-- #       : username string
SELECT layout
FROM bw_shop_layout
WHERE username = :username
LIMIT 1;
-- #     }

-- #     { set
-- #       : username string
-- #       : layout string
-- #       : updated int
INSERT INTO bw_shop_layout (username, layout, updated_at)
VALUES (:username, :layout, :updated)
ON DUPLICATE KEY UPDATE
    layout = VALUES(layout),
    updated_at = VALUES(updated_at);
-- #     }

-- #   }

-- #   { clan

-- #     { create_table
CREATE TABLE IF NOT EXISTS bw_clans (
    id                  VARCHAR(40)  PRIMARY KEY,
    name                VARCHAR(32)  NOT NULL,
    tag                 VARCHAR(8)   NOT NULL,
    color               VARCHAR(16)  NOT NULL DEFAULT 'WHITE',
    description         VARCHAR(255) NOT NULL DEFAULT '',
    crest_id            VARCHAR(32)  NOT NULL DEFAULT 'shield_blue',
    owner_username      VARCHAR(36)  NOT NULL,
    visibility          VARCHAR(16)  NOT NULL DEFAULT 'PUBLIC',
    required_level      INT          NOT NULL DEFAULT 1,
    xp                  INT          NOT NULL DEFAULT 0,
    weekly_xp           INT          NOT NULL DEFAULT 0,
    weekly_wins         INT          NOT NULL DEFAULT 0,
    weekly_final_kills  INT          NOT NULL DEFAULT 0,
    level               INT          NOT NULL DEFAULT 1,
    bank_coins          INT          NOT NULL DEFAULT 0,
    last_week_score     INT          NOT NULL DEFAULT 0,
    last_week_rank      INT          NOT NULL DEFAULT 0,
    best_week_rank      INT          NOT NULL DEFAULT 0,
    created_at          INT          NOT NULL,
    UNIQUE KEY uniq_clan_name (name),
    UNIQUE KEY uniq_clan_tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { members_create_table
CREATE TABLE IF NOT EXISTS bw_clan_members (
    username    VARCHAR(36) PRIMARY KEY,
    clan_id     VARCHAR(40) NOT NULL,
    role        VARCHAR(16) NOT NULL DEFAULT 'MEMBER',
    joined_at   INT NOT NULL,
    INDEX idx_clan_members_clan (clan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { applications_create_table
CREATE TABLE IF NOT EXISTS bw_clan_applications (
    id          VARCHAR(40)  PRIMARY KEY,
    clan_id     VARCHAR(40)  NOT NULL,
    username    VARCHAR(36)  NOT NULL,
    message     VARCHAR(255) NOT NULL DEFAULT '',
    status      VARCHAR(16)  NOT NULL DEFAULT 'pending',
    reason      VARCHAR(255) NOT NULL DEFAULT '',
    created_at  INT NOT NULL,
    INDEX idx_clan_app_clan_status (clan_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { creation_requests_create_table
CREATE TABLE IF NOT EXISTS bw_clan_creation_requests (
    id              VARCHAR(40)  PRIMARY KEY,
    username        VARCHAR(36)  NOT NULL,
    name            VARCHAR(32)  NOT NULL,
    tag             VARCHAR(8)   NOT NULL,
    color           VARCHAR(16)  NOT NULL DEFAULT 'WHITE',
    description     VARCHAR(255) NOT NULL DEFAULT '',
    crest_id        VARCHAR(32)  NOT NULL DEFAULT 'shield_blue',
    visibility      VARCHAR(16)  NOT NULL DEFAULT 'PUBLIC',
    required_level  INT NOT NULL DEFAULT 1,
    cost            INT NOT NULL DEFAULT 0,
    status          VARCHAR(16)  NOT NULL DEFAULT 'pending',
    reason          VARCHAR(255) NOT NULL DEFAULT '',
    created_at      INT NOT NULL,
    INDEX idx_clan_creation_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { bank_log_create_table
CREATE TABLE IF NOT EXISTS bw_clan_bank_log (
    id          VARCHAR(40) PRIMARY KEY,
    clan_id     VARCHAR(40) NOT NULL,
    username    VARCHAR(36) NOT NULL,
    type        VARCHAR(16) NOT NULL,
    amount      INT NOT NULL,
    created_at  INT NOT NULL,
    INDEX idx_clan_bank_clan (clan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { levelups_create_table
CREATE TABLE IF NOT EXISTS bw_clan_levelups (
    id           VARCHAR(40) PRIMARY KEY,
    clan_id      VARCHAR(40) NOT NULL,
    level        INT NOT NULL,
    achieved_at  INT NOT NULL,
    INDEX idx_clan_levelups_clan (clan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { insert
-- #       : id string
-- #       : name string
-- #       : tag string
-- #       : color string
-- #       : description string
-- #       : crest_id string
-- #       : owner_username string
-- #       : visibility string
-- #       : required_level int
-- #       : created_at int
INSERT INTO bw_clans (id, name, tag, color, description, crest_id, owner_username, visibility, required_level, created_at)
VALUES (:id, :name, :tag, :color, :description, :crest_id, :owner_username, :visibility, :required_level, :created_at);
-- #     }

-- #     { get_by_id
-- #       : id string
SELECT * FROM bw_clans WHERE id = :id LIMIT 1;
-- #     }

-- #     { get_by_tag
-- #       : tag string
SELECT * FROM bw_clans WHERE tag = :tag LIMIT 1;
-- #     }

-- #     { get_by_name
-- #       : name string
SELECT * FROM bw_clans WHERE name = :name LIMIT 1;
-- #     }

-- #     { get_all
SELECT * FROM bw_clans;
-- #     }

-- #     { update_meta
-- #       : id string
-- #       : name string
-- #       : tag string
-- #       : color string
-- #       : description string
-- #       : crest_id string
-- #       : visibility string
-- #       : required_level int
UPDATE bw_clans SET
    name = :name,
    tag = :tag,
    color = :color,
    description = :description,
    crest_id = :crest_id,
    visibility = :visibility,
    required_level = :required_level
WHERE id = :id;
-- #     }

-- #     { update_owner
-- #       : id string
-- #       : owner_username string
UPDATE bw_clans SET owner_username = :owner_username WHERE id = :id;
-- #     }

-- #     { add_xp
-- #       : id string
-- #       : amount int
UPDATE bw_clans SET xp = xp + :amount, weekly_xp = weekly_xp + :amount WHERE id = :id;
-- #     }

-- #     { add_weekly_combat
-- #       : id string
-- #       : wins_delta int
-- #       : final_kills_delta int
UPDATE bw_clans SET weekly_wins = weekly_wins + :wins_delta, weekly_final_kills = weekly_final_kills + :final_kills_delta WHERE id = :id;
-- #     }

-- #     { set_level
-- #       : id string
-- #       : level int
UPDATE bw_clans SET level = :level WHERE id = :id;
-- #     }

-- #     { add_bank
-- #       : id string
-- #       : amount int
UPDATE bw_clans SET bank_coins = bank_coins + :amount WHERE id = :id;
-- #     }

-- #     { withdraw_bank
-- #       : id string
-- #       : amount int
UPDATE bw_clans SET bank_coins = bank_coins - :amount WHERE id = :id AND bank_coins >= :amount;
-- #     }

-- #     { reset_weekly
-- #       : id string
UPDATE bw_clans SET weekly_xp = 0, weekly_wins = 0, weekly_final_kills = 0 WHERE id = :id;
-- #     }

-- #     { set_last_week
-- #       : id string
-- #       : score int
-- #       : rank int
-- #       : best_rank int
UPDATE bw_clans SET last_week_score = :score, last_week_rank = :rank, best_week_rank = :best_rank WHERE id = :id;
-- #     }

-- #     { delete
-- #       : id string
DELETE FROM bw_clans WHERE id = :id;
-- #     }

-- #     { member_insert
-- #       : username string
-- #       : clan_id string
-- #       : role string
-- #       : joined_at int
INSERT INTO bw_clan_members (username, clan_id, role, joined_at)
VALUES (:username, :clan_id, :role, :joined_at)
ON DUPLICATE KEY UPDATE clan_id = VALUES(clan_id), role = VALUES(role), joined_at = VALUES(joined_at);
-- #     }

-- #     { member_delete
-- #       : username string
DELETE FROM bw_clan_members WHERE username = :username;
-- #     }

-- #     { member_get_by_username
-- #       : username string
SELECT * FROM bw_clan_members WHERE username = :username LIMIT 1;
-- #     }

-- #     { member_get_roster
-- #       : clan_id string
SELECT * FROM bw_clan_members WHERE clan_id = :clan_id;
-- #     }

-- #     { member_get_all
SELECT * FROM bw_clan_members;
-- #     }

-- #     { member_set_role
-- #       : username string
-- #       : role string
UPDATE bw_clan_members SET role = :role WHERE username = :username;
-- #     }

-- #     { application_insert
-- #       : id string
-- #       : clan_id string
-- #       : username string
-- #       : message string
-- #       : created_at int
INSERT INTO bw_clan_applications (id, clan_id, username, message, status, created_at)
VALUES (:id, :clan_id, :username, :message, 'pending', :created_at);
-- #     }

-- #     { application_get_pending_for_clan
-- #       : clan_id string
SELECT * FROM bw_clan_applications WHERE clan_id = :clan_id AND status = 'pending' ORDER BY created_at ASC;
-- #     }

-- #     { application_get_pending_for_user
-- #       : clan_id string
-- #       : username string
SELECT * FROM bw_clan_applications WHERE clan_id = :clan_id AND username = :username AND status = 'pending' LIMIT 1;
-- #     }

-- #     { application_get_by_id
-- #       : id string
SELECT * FROM bw_clan_applications WHERE id = :id LIMIT 1;
-- #     }

-- #     { application_set_status
-- #       : id string
-- #       : status string
-- #       : reason string
UPDATE bw_clan_applications SET status = :status, reason = :reason WHERE id = :id;
-- #     }

-- #     { creation_request_insert
-- #       : id string
-- #       : username string
-- #       : name string
-- #       : tag string
-- #       : color string
-- #       : description string
-- #       : crest_id string
-- #       : visibility string
-- #       : required_level int
-- #       : cost int
-- #       : created_at int
INSERT INTO bw_clan_creation_requests (id, username, name, tag, color, description, crest_id, visibility, required_level, cost, status, created_at)
VALUES (:id, :username, :name, :tag, :color, :description, :crest_id, :visibility, :required_level, :cost, 'pending', :created_at);
-- #     }

-- #     { creation_request_get_all_pending
SELECT * FROM bw_clan_creation_requests WHERE status = 'pending' ORDER BY created_at ASC;
-- #     }

-- #     { creation_request_get_pending_for_user
-- #       : username string
SELECT * FROM bw_clan_creation_requests WHERE username = :username AND status = 'pending' LIMIT 1;
-- #     }

-- #     { creation_request_get_by_id
-- #       : id string
SELECT * FROM bw_clan_creation_requests WHERE id = :id LIMIT 1;
-- #     }

-- #     { creation_request_set_status
-- #       : id string
-- #       : status string
-- #       : reason string
UPDATE bw_clan_creation_requests SET status = :status, reason = :reason WHERE id = :id;
-- #     }

-- #     { bank_log_insert
-- #       : id string
-- #       : clan_id string
-- #       : username string
-- #       : type string
-- #       : amount int
-- #       : created_at int
INSERT INTO bw_clan_bank_log (id, clan_id, username, type, amount, created_at)
VALUES (:id, :clan_id, :username, :type, :amount, :created_at);
-- #     }

-- #     { bank_log_get_recent
-- #       : clan_id string
-- #       : limit int
SELECT * FROM bw_clan_bank_log WHERE clan_id = :clan_id ORDER BY created_at DESC LIMIT :limit;
-- #     }

-- #     { bank_log_get_daily_deposit_total
-- #       : clan_id string
-- #       : username string
-- #       : since int
SELECT COALESCE(SUM(amount), 0) AS total FROM bw_clan_bank_log
WHERE clan_id = :clan_id AND username = :username AND type = 'deposit' AND created_at >= :since;
-- #     }

-- #     { levelup_insert
-- #       : id string
-- #       : clan_id string
-- #       : level int
-- #       : achieved_at int
INSERT INTO bw_clan_levelups (id, clan_id, level, achieved_at)
VALUES (:id, :clan_id, :level, :achieved_at);
-- #     }

-- #     { levelup_get_recent
-- #       : clan_id string
-- #       : limit int
SELECT level, achieved_at FROM bw_clan_levelups WHERE clan_id = :clan_id ORDER BY achieved_at DESC LIMIT :limit;
-- #     }

-- #   }

-- #   { report

-- #     { create_table
CREATE TABLE IF NOT EXISTS bw_reports (
    id              VARCHAR(40)  PRIMARY KEY,
    reporter        VARCHAR(36)  NOT NULL,
    reported        VARCHAR(36)  NOT NULL,
    reason_id       VARCHAR(32)  NOT NULL,
    description     VARCHAR(255) NOT NULL DEFAULT '',
    server_name     VARCHAR(64)  NOT NULL DEFAULT '',
    game_id         INT          NOT NULL DEFAULT 0,
    status          VARCHAR(16)  NOT NULL DEFAULT 'pending',
    created_at      INT          NOT NULL,
    INDEX idx_reports_reported (reported),
    INDEX idx_reports_reporter_created (reporter, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { insert
-- #       : id string
-- #       : reporter string
-- #       : reported string
-- #       : reason_id string
-- #       : description string
-- #       : server_name string
-- #       : game_id int
-- #       : created_at int
INSERT INTO bw_reports (id, reporter, reported, reason_id, description, server_name, game_id, created_at)
VALUES (:id, :reporter, :reported, :reason_id, :description, :server_name, :game_id, :created_at);
-- #     }

-- #     { get_recent
-- #       : limit int
SELECT * FROM bw_reports ORDER BY created_at DESC LIMIT :limit;
-- #     }

-- #     { get_recent_against
-- #       : reported string
-- #       : limit int
SELECT * FROM bw_reports WHERE reported = :reported ORDER BY created_at DESC LIMIT :limit;
-- #     }

-- #     { count_against_since
-- #       : reported string
-- #       : since int
SELECT COUNT(*) AS total FROM bw_reports WHERE reported = :reported AND created_at >= :since;
-- #     }

-- #   }

-- #   { profile

-- #     { create_profile
CREATE TABLE IF NOT EXISTS bw_profile (
    username      VARCHAR(36)  PRIMARY KEY,
    display_name  VARCHAR(36)  NOT NULL DEFAULT '',
    games         INT          NOT NULL DEFAULT 0,
    losses        INT          NOT NULL DEFAULT 0,
    playtime      INT          NOT NULL DEFAULT 0,
    mvps          INT          NOT NULL DEFAULT 0,
    rp            INT          NOT NULL DEFAULT 0,
    peak_rp       INT          NOT NULL DEFAULT 0,
    peak_tier     INT          NOT NULL DEFAULT 0,
    updated_at    INT          NOT NULL DEFAULT 0,
    INDEX idx_profile_rp (rp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { create_medals
CREATE TABLE IF NOT EXISTS bw_medals (
    username  VARCHAR(36) NOT NULL,
    medal     VARCHAR(32) NOT NULL,
    cnt       INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (username, medal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { create_achievements
CREATE TABLE IF NOT EXISTS bw_achievements (
    username     VARCHAR(36) NOT NULL,
    achievement  VARCHAR(32) NOT NULL,
    unlocked_at  INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (username, achievement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { create_history
CREATE TABLE IF NOT EXISTS bw_match_history (
    id        BIGINT      AUTO_INCREMENT PRIMARY KEY,
    username  VARCHAR(36) NOT NULL,
    ts        INT         NOT NULL DEFAULT 0,
    mode      VARCHAR(16) NOT NULL DEFAULT 'Solo',
    map       VARCHAR(48) NOT NULL DEFAULT '',
    result    VARCHAR(8)  NOT NULL DEFAULT 'LOSS',
    kills     INT         NOT NULL DEFAULT 0,
    finals    INT         NOT NULL DEFAULT 0,
    beds      INT         NOT NULL DEFAULT 0,
    deaths    INT         NOT NULL DEFAULT 0,
    rp_delta  INT         NOT NULL DEFAULT 0,
    duration  INT         NOT NULL DEFAULT 0,
    season    INT         NOT NULL DEFAULT 1,
    INDEX idx_history_user (username, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { create_seasons
CREATE TABLE IF NOT EXISTS bw_seasons (
    id          INT         PRIMARY KEY,
    name        VARCHAR(32) NOT NULL DEFAULT '',
    started_at  INT         NOT NULL DEFAULT 0,
    ends_at     INT         NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { create_season_results
CREATE TABLE IF NOT EXISTS bw_season_results (
    username  VARCHAR(36) NOT NULL,
    season    INT         NOT NULL,
    peak_rp   INT         NOT NULL DEFAULT 0,
    final_rp  INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (username, season)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #     }

-- #     { seed_season
-- #       : now int
-- #       : ends int
INSERT IGNORE INTO bw_seasons (id, name, started_at, ends_at) VALUES (1, '', :now, :ends);
-- #     }

-- #     { get_season
SELECT id, name, started_at, ends_at FROM bw_seasons ORDER BY id DESC LIMIT 1;
-- #     }

-- #     { new_season
-- #       : id int
-- #       : name string
-- #       : now int
-- #       : ends int
INSERT IGNORE INTO bw_seasons (id, name, started_at, ends_at) VALUES (:id, :name, :now, :ends);
-- #     }

-- #     { archive_season
-- #       : season int
INSERT IGNORE INTO bw_season_results (username, season, peak_rp, final_rp)
SELECT username, :season, peak_rp, rp FROM bw_profile WHERE peak_rp > 0 OR rp > 0;
-- #     }

-- #     { reset_season
UPDATE bw_profile SET rp = 0, peak_rp = 0 WHERE rp > 0 OR peak_rp > 0;
-- #     }

-- #     { ensure
-- #       : username string
-- #       : display string
-- #       : now int
INSERT INTO bw_profile (username, display_name, updated_at) VALUES (:username, :display, :now)
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);
-- #     }

-- #     { add_playtime
-- #       : username string
-- #       : display string
-- #       : secs int
-- #       : now int
INSERT INTO bw_profile (username, display_name, playtime, updated_at) VALUES (:username, :display, :secs, :now)
ON DUPLICATE KEY UPDATE playtime = playtime + VALUES(playtime), display_name = VALUES(display_name), updated_at = VALUES(updated_at);
-- #     }

-- #     { get_profile
-- #       : username string
SELECT display_name, games, losses, playtime, mvps, rp, peak_rp, peak_tier FROM bw_profile WHERE username = :username LIMIT 1;
-- #     }

-- #     { get_stats
-- #       : username string
SELECT username, kills, wins, beds_broken, final_kills, deaths, xp, level, win_streak, best_win_streak FROM bw_players WHERE username = :username LIMIT 1;
-- #     }

-- #     { rank_position
-- #       : rp int
SELECT COUNT(*) AS higher FROM bw_profile WHERE rp > :rp;
-- #     }

-- #     { apply_match
-- #       : username string
-- #       : display string
-- #       : loss int
-- #       : mvp int
-- #       : rp int
-- #       : tier int
-- #       : now int
INSERT INTO bw_profile (username, display_name, games, losses, mvps, rp, peak_rp, peak_tier, updated_at)
VALUES (:username, :display, 1, :loss, :mvp, :rp, :rp, :tier, :now)
ON DUPLICATE KEY UPDATE
    games = games + 1,
    losses = losses + VALUES(losses),
    mvps = mvps + VALUES(mvps),
    rp = VALUES(rp),
    peak_rp = GREATEST(peak_rp, VALUES(rp)),
    peak_tier = GREATEST(peak_tier, VALUES(peak_tier)),
    display_name = VALUES(display_name),
    updated_at = VALUES(updated_at);
-- #     }

-- #     { add_medal
-- #       : username string
-- #       : medal string
-- #       : cnt int
INSERT INTO bw_medals (username, medal, cnt) VALUES (:username, :medal, :cnt)
ON DUPLICATE KEY UPDATE cnt = cnt + VALUES(cnt);
-- #     }

-- #     { get_medals
-- #       : username string
SELECT medal, cnt FROM bw_medals WHERE username = :username;
-- #     }

-- #     { unlock_achievement
-- #       : username string
-- #       : achievement string
-- #       : now int
INSERT IGNORE INTO bw_achievements (username, achievement, unlocked_at) VALUES (:username, :achievement, :now);
-- #     }

-- #     { insert_history
-- #       : username string
-- #       : ts int
-- #       : mode string
-- #       : map string
-- #       : result string
-- #       : kills int
-- #       : finals int
-- #       : beds int
-- #       : deaths int
-- #       : rp_delta int
-- #       : duration int
-- #       : season int
INSERT INTO bw_match_history (username, ts, mode, map, result, kills, finals, beds, deaths, rp_delta, duration, season)
VALUES (:username, :ts, :mode, :map, :result, :kills, :finals, :beds, :deaths, :rp_delta, :duration, :season);
-- #     }

-- #     { get_history
-- #       : username string
-- #       : limit int
-- #       : offset int
SELECT ts, mode, map, result, kills, finals, beds, deaths, rp_delta, duration FROM bw_match_history WHERE username = :username ORDER BY id DESC LIMIT :limit OFFSET :offset;
-- #     }

-- #     { count_history
-- #       : username string
SELECT COUNT(*) AS total FROM bw_match_history WHERE username = :username;
-- #     }

-- #     { season_results
-- #       : username string
-- #       : limit int
SELECT season, peak_rp, final_rp FROM bw_season_results WHERE username = :username ORDER BY season DESC LIMIT :limit;
-- #     }

-- #     { top
-- #       : limit int
SELECT display_name, username, rp, peak_tier FROM bw_profile WHERE rp > 0 ORDER BY rp DESC, peak_rp DESC, games ASC LIMIT :limit;
-- #     }

-- #   }


-- # }