<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan;

use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\clan\application\ClanApplication;
use sergittos\bedwars\clan\data\PlayerClanDataManager;
use sergittos\bedwars\clan\request\ClanCreationRequest;
use sergittos\bedwars\session\Session;

/**
 * Central clan registry. Keeps every clan cached in memory (there are never
 * more than a few hundred of these, unlike players, so this is cheap) and
 * refreshed periodically from MySQL by ClanRefreshTask in BedWarsLobby - the
 * same "authoritative DB, warm in-memory cache" shape LeaderboardManager
 * already uses for stat holograms.
 *
 * Every mutation here does two things: it updates the local cache
 * immediately (so the GUI that triggered it feels instant) and fires the
 * matching async write to MySQL, which is also what every other Game server
 * on the network reads back on its own periodic refresh - or, for XP itself,
 * writes to directly and atomically (see ClanConfig + Session hooks in
 * BedWarsCore-game), so simultaneous games on different servers can never
 * race each other or this cache.
 */
final class ClanManager{

    /** @var array<string,Clan> clan id => Clan */
    private array $clans = [];

    /** @var array<string,string> lowercase username => clan id, derived from $clans on every refresh */
    private array $memberIndex = [];

    /**
     * Pending clan invites (owner/officer -> target player), matching the
     * in-memory, lazily-expired shape PartyManager/Party already use for
     * party invites. Invite-only clans go through applications instead;
     * this is only ever used for the "Invite Player" flow.
     * @var array<string,array{clanId:string,inviter:string,expiresAt:int}> lowercase target username => invite
     */
    private array $pendingInvites = [];

    private ClanConfig $config;
    private PlayerClanDataManager $playerData;

    public function __construct(private BedWarsCore $plugin){
        $this->config = new ClanConfig($plugin);
        $this->playerData = new PlayerClanDataManager($plugin);
    }

    public function getConfig(): ClanConfig{ return $this->config; }
    public function getPlayerData(): PlayerClanDataManager{ return $this->playerData; }

    // ==========================================================================
    // Cache
    // ==========================================================================

    public function refreshAll(?callable $onDone = null): void{
        $provider = $this->plugin->getProvider();

        $provider->getAllClanMembers(function(array $memberRows) use ($provider, $onDone): void{
            $membersByClan = [];
            foreach($memberRows as $row){
                $membersByClan[(string) $row["clan_id"]][strtolower((string) $row["username"])] = (string) $row["role"];
            }

            $provider->getAllClans(function(array $clanRows) use ($membersByClan, $onDone): void{
                $clans = [];
                $memberIndex = [];

                foreach($clanRows as $row){
                    $id = (string) $row["id"];
                    $clan = new Clan($row, $membersByClan[$id] ?? []);
                    $clans[$id] = $clan;
                    foreach($clan->getMembers() as $username => $role){
                        $memberIndex[$username] = $id;
                    }
                }

                $this->clans = $clans;
                $this->memberIndex = $memberIndex;

                $this->checkLevelUps($clans);

                if($onDone !== null){
                    $onDone();
                }
            });
        });
    }

    /**
     * Compares each freshly-loaded clan's XP against the level thresholds
     * and promotes it (persisting the new level + a levelup log row +
     * notifying online members) if it has out-leveled its cached level.
     * This is the single place level-up detection happens - Game servers
     * only ever add raw XP, they never compute levels themselves.
     *
     * @param array<string,Clan> $clans
     */
    private function checkLevelUps(array $clans): void{
        foreach($clans as $clan){
            $level = $clan->getLevel();
            $xp = $clan->getXp();

            $leveled = false;
            while($xp >= $this->config->getRequiredXpForLevel($level)){
                $xp -= $this->config->getRequiredXpForLevel($level);
                $level++;
                $leveled = true;
            }

            if($leveled){
                $clan->setLevelLocal($level);
                $this->plugin->getProvider()->setClanLevel($clan->getId(), $level);
                $this->plugin->getProvider()->addClanLevelup($clan->getId(), $level);
                $this->notifyClan($clan, PlayerClanDataManager::NOTIFY_CLAN_LEVEL_UP, "Your clan " . $clan->getColoredName() . " reached level " . $level . "!");
            }
        }
    }

    public function getById(string $id): ?Clan{
        return $this->clans[$id] ?? null;
    }

    public function getByTag(string $tag): ?Clan{
        $tag = strtolower($tag);
        foreach($this->clans as $clan){
            if(strtolower($clan->getTag()) === $tag){
                return $clan;
            }
        }
        return null;
    }

    public function getByName(string $name): ?Clan{
        $name = strtolower($name);
        foreach($this->clans as $clan){
            if(strtolower($clan->getName()) === $name){
                return $clan;
            }
        }
        return null;
    }

    public function getClanOf(string $username): ?Clan{
        $id = $this->memberIndex[strtolower($username)] ?? null;
        return $id !== null ? ($this->clans[$id] ?? null) : null;
    }

    public function isInClan(string $username): bool{
        return isset($this->memberIndex[strtolower($username)]);
    }

    /** @return Clan[] */
    public function getAll(): array{
        return array_values($this->clans);
    }

    /**
     * In-memory directory search/filter, matching ClanDirectoryGui's form.
     * @return Clan[]
     */
    public function search(?string $query, ?int $minLevel, ?int $maxLevel, ?int $minMembers, ?int $maxMembers, bool $publicOnly): array{
        $query = $query !== null ? strtolower(trim($query)) : null;

        $results = array_filter($this->clans, function(Clan $clan) use ($query, $minLevel, $maxLevel, $minMembers, $maxMembers, $publicOnly): bool{
            if($publicOnly && !$clan->isPublic()) return false;
            if($query !== null && $query !== "" && !str_contains(strtolower($clan->getName()), $query) && !str_contains(strtolower($clan->getTag()), $query)) return false;
            if($minLevel !== null && $clan->getLevel() < $minLevel) return false;
            if($maxLevel !== null && $clan->getLevel() > $maxLevel) return false;
            if($minMembers !== null && $clan->getMemberCount() < $minMembers) return false;
            if($maxMembers !== null && $clan->getMemberCount() > $maxMembers) return false;
            return true;
        });

        $results = array_values($results);
        usort($results, fn(Clan $a, Clan $b) => $b->getLevel() <=> $a->getLevel());
        return $results;
    }

    /** @return Clan[] top clans by current weekly score, descending */
    public function getWeeklyLeaderboard(int $limit = 10): array{
        $clans = $this->getAll();
        usort($clans, fn(Clan $a, Clan $b) => $b->computeWeeklyScore($this->config) <=> $a->computeWeeklyScore($this->config));
        return array_slice($clans, 0, $limit);
    }

    // ==========================================================================
    // Validation
    // ==========================================================================

    public function isNameAvailable(string $name): bool{
        return $this->getByName($name) === null;
    }

    public function isTagAvailable(string $tag): bool{
        return $this->getByTag($tag) === null;
    }

    public function validateName(string $name): ?string{
        $len = mb_strlen($name);
        if($len < $this->config->getNameMinLength() || $len > $this->config->getNameMaxLength()){
            return "Clan name must be between " . $this->config->getNameMinLength() . " and " . $this->config->getNameMaxLength() . " characters.";
        }
        if(!preg_match('/^[\p{L}\p{N} _\-\']+$/u', $name)){
            return "Clan name contains invalid characters.";
        }
        if(!$this->isNameAvailable($name)){
            return "That clan name is already taken.";
        }
        return null;
    }

    public function validateTag(string $tag): ?string{
        $len = mb_strlen($tag);
        if($len < $this->config->getTagMinLength() || $len > $this->config->getTagMaxLength()){
            return "Clan tag must be between " . $this->config->getTagMinLength() . " and " . $this->config->getTagMaxLength() . " characters.";
        }
        if(!preg_match('/^[A-Za-z0-9]+$/', $tag)){
            return "Clan tag may only contain letters and numbers.";
        }
        if(!$this->isTagAvailable($tag)){
            return "That clan tag is already taken.";
        }
        return null;
    }

    public function validateDescription(string $description): ?string{
        if(mb_strlen($description) > $this->config->getDescriptionMaxLength()){
            return "Description is too long (max " . $this->config->getDescriptionMaxLength() . " characters).";
        }
        return null;
    }

    // ==========================================================================
    // Creation flow (op-approved)
    // ==========================================================================

    /** @param callable(bool,string):void $callback */
    public function requestCreation(Session $session, array $draft, callable $callback): void{
        $username = $session->getPlayer()->getName();

        if($this->isInClan($username)){
            $callback(false, "You are already in a clan.");
            return;
        }

        $name = trim((string) $draft["name"]);
        $tag = trim((string) $draft["tag"]);
        $description = trim((string) $draft["description"]);
        $crestId = (string) $draft["crest_id"];
        $color = (string) $draft["color"];
        $visibility = (string) $draft["visibility"];
        $requiredLevel = (int) $draft["required_level"];

        if(($error = $this->validateName($name)) !== null){ $callback(false, $error); return; }
        if(($error = $this->validateTag($tag)) !== null){ $callback(false, $error); return; }
        if(($error = $this->validateDescription($description)) !== null){ $callback(false, $error); return; }
        if(!ClanCrestRegistry::exists($crestId)){ $callback(false, "Invalid crest selection."); return; }

        $cost = $this->config->getCreationCost();
        if($session->getCoins() < $cost){
            $callback(false, "You need " . $cost . " coins to request a clan (you have " . $session->getCoins() . ").");
            return;
        }

        $provider = $this->plugin->getProvider();
        $provider->getPendingCreationRequestForUser($username, function(?array $existing) use ($provider, $username, $name, $tag, $color, $description, $crestId, $visibility, $requiredLevel, $cost, $callback): void{
            if($existing !== null){
                $callback(false, "You already have a clan creation request awaiting review.");
                return;
            }

            $provider->createClanCreationRequest([
                "id"             => uniqid("ccr_", true),
                "username"       => $username,
                "name"           => $name,
                "tag"            => $tag,
                "color"          => $color,
                "description"    => $description,
                "crest_id"       => $crestId,
                "visibility"     => $visibility,
                "required_level" => $requiredLevel,
                "cost"           => $cost,
            ]);

            $callback(true, "Your clan request was submitted for staff review.");
        });
    }

    public function getPendingCreationRequests(callable $callback): void{
        $this->plugin->getProvider()->getAllPendingCreationRequests(function(array $rows) use ($callback): void{
            $callback(array_map(fn(array $row) => ClanCreationRequest::fromRow($row), $rows));
        });
    }

    public function getPendingCreationRequestFor(string $username, callable $callback): void{
        $this->plugin->getProvider()->getPendingCreationRequestForUser($username, function(?array $row) use ($callback): void{
            $callback($row !== null ? ClanCreationRequest::fromRow($row) : null);
        });
    }

    /** @param callable(bool,string):void $callback */
    public function approveCreationRequest(string $requestId, callable $callback): void{
        $provider = $this->plugin->getProvider();

        $provider->getClanCreationRequestById($requestId, function(?array $row) use ($provider, $callback): void{
            if($row === null || $row["status"] !== "pending"){
                $callback(false, "This request is no longer pending.");
                return;
            }

            $request = ClanCreationRequest::fromRow($row);

            // Re-validate uniqueness against the live cache in case another
            // clan grabbed the name/tag while this request was waiting.
            if(!$this->isNameAvailable($request->name)){
                $provider->setClanCreationRequestStatus($request->id, "rejected", "Name was taken while this request was pending.");
                $callback(false, "That clan name has since been taken - request auto-rejected.");
                return;
            }
            if(!$this->isTagAvailable($request->tag)){
                $provider->setClanCreationRequestStatus($request->id, "rejected", "Tag was taken while this request was pending.");
                $callback(false, "That clan tag has since been taken - request auto-rejected.");
                return;
            }

            $chargeCallback = function(bool $charged) use ($provider, $request, $callback): void{
                if(!$charged){
                    $provider->setClanCreationRequestStatus($request->id, "rejected", "Insufficient coins at approval time.");
                    $callback(false, "The requester no longer has enough coins - request auto-cancelled.");
                    return;
                }

                $id = uniqid("clan_", true);
                $provider->createClan(
                    [
                        "id"              => $id,
                        "name"            => $request->name,
                        "tag"             => $request->tag,
                        "color"           => $request->color,
                        "description"     => $request->description,
                        "crest_id"        => $request->crestId,
                        "owner_username"  => $request->username,
                        "visibility"      => $request->visibility,
                        "required_level"  => $request->requiredLevel,
                    ],
                    function() use ($provider, $id, $request, $callback): void{
                        $provider->upsertClanMember($request->username, $id, ClanRole::OWNER);
                        $provider->setClanCreationRequestStatus($request->id, "approved");
                        $this->refreshAll();
                        $callback(true, "Clan \"" . $request->name . "\" was approved and created.");
                    },
                    function() use ($provider, $request, $callback): void{
                        // Insert failed (most likely a race on the unique
                        // name/tag constraint) - refund the requester.
                        $this->refundOrCredit($request->username, $request->cost);
                        $provider->setClanCreationRequestStatus($request->id, "rejected", "Creation failed, coins refunded.");
                        $callback(false, "Clan creation failed (name/tag was just taken) - coins were refunded.");
                    }
                );
            };

            $this->chargeCoins($request->username, $request->cost, $chargeCallback);
        });
    }

    public function rejectCreationRequest(string $requestId, string $reason, callable $callback): void{
        $this->plugin->getProvider()->setClanCreationRequestStatus($requestId, "rejected", $reason);
        $callback(true, "Request rejected.");
    }

    // ==========================================================================
    // Coin helpers - works whether the target player is online or not, since
    // op approval can legitimately happen while the requester is offline.
    // ==========================================================================

    private function chargeCoins(string $username, int $amount, callable $callback): void{
        $session = $this->plugin->getSessionManager()->getByName($username);
        if($session !== null){
            if($session->getCoins() < $amount){
                $callback(false);
                return;
            }
            $session->setCoins($session->getCoins() - $amount);
            $callback(true);
            return;
        }
        $this->plugin->getProvider()->deductPlayerCoinsRaw($username, $amount, $callback);
    }

    /**
     * Refunding an online player is trivial (Session::addCoins). Refunding
     * an *offline* player only happens on the rare race where two approvals
     * land on the same name/tag within the same instant - rather than add a
     * whole extra unconditional-credit query path for that edge case, we
     * log it clearly so staff can credit it by hand.
     */
    private function refundOrCredit(string $username, int $amount): void{
        $session = $this->plugin->getSessionManager()->getByName($username);
        if($session !== null){
            $session->addCoins($amount);
            return;
        }
        $this->plugin->getLogger()->warning("Clan creation refund of {$amount} coins for offline player {$username} could not be applied automatically - please credit manually.");
    }

    // ==========================================================================
    // Instant join (public clans) - no review needed, matches the green
    // "Join Clan" button on a public clan's profile in the directory.
    // ==========================================================================

    /** @param callable(bool,string):void $callback */
    public function joinPublicClan(Session $session, Clan $clan, callable $callback): void{
        $username = $session->getPlayer()->getName();

        if($this->isInClan($username)){ $callback(false, "You are already in a clan."); return; }
        if(!$clan->isPublic()){ $callback(false, "This clan is invite-only - send an application instead."); return; }
        if($clan->isFull($this->config)){ $callback(false, "This clan is full."); return; }
        if($session->getLevel() < $clan->getRequiredLevel()){ $callback(false, "You need to be level " . $clan->getRequiredLevel() . "+ to join."); return; }

        $this->playerData->getCooldownUntil($username, function(int $cooldown) use ($session, $clan, $username, $callback): void{
            if($cooldown > time()){
                $callback(false, "You can't join a new clan for another " . $this->formatDuration($cooldown - time()) . ".");
                return;
            }

            $this->plugin->getProvider()->upsertClanMember($username, $clan->getId(), ClanRole::MEMBER);
            $clan->setMemberLocal($username, ClanRole::MEMBER);
            $this->memberIndex[strtolower($username)] = $clan->getId();
            $this->notifyClan($clan, PlayerClanDataManager::NOTIFY_MEMBER_JOIN, $username . " joined the clan!");
            $callback(true, "You joined " . $clan->getColoredName() . TF::RESET . "!");
        });
    }

    // ==========================================================================
    // Applications (invite-only clans) - reviewed by an owner/officer
    // through the Applications screen.
    // ==========================================================================

    /** @param callable(bool,string):void $callback */
    public function applyToJoin(Session $session, Clan $clan, string $message, callable $callback): void{
        $username = $session->getPlayer()->getName();

        if($this->isInClan($username)){ $callback(false, "You are already in a clan."); return; }
        if($clan->isPublic()){ $callback(false, "This clan is public - you can join it directly."); return; }
        if($clan->isFull($this->config)){ $callback(false, "This clan is full."); return; }
        if($session->getLevel() < $clan->getRequiredLevel()){ $callback(false, "You need to be level " . $clan->getRequiredLevel() . "+ to apply."); return; }

        $this->playerData->getCooldownUntil($username, function(int $cooldown) use ($session, $clan, $message, $username, $callback): void{
            if($cooldown > time()){
                $callback(false, "You can't join a new clan for another " . $this->formatDuration($cooldown - time()) . ".");
                return;
            }

            $provider = $this->plugin->getProvider();
            $provider->getPendingApplicationForUser($clan->getId(), $username, function(?array $existing) use ($provider, $clan, $username, $message, $callback): void{
                if($existing !== null){
                    $callback(false, "You already have a pending application to this clan.");
                    return;
                }

                $provider->createClanApplication(
                    uniqid("capp_", true), $clan->getId(), $username, mb_substr(trim($message), 0, 255),
                    function() use ($clan, $callback): void{
                        $callback(true, "Application sent to " . $clan->getColoredName() . \pocketmine\utils\TextFormat::RESET . ".");
                    },
                    function() use ($callback): void{
                        $callback(false, "Something went wrong sending your application - please try again.");
                    }
                );
            });
        });
    }

    public function getPendingApplications(Clan $clan, callable $callback): void{
        $this->plugin->getProvider()->getPendingApplicationsForClan($clan->getId(), function(array $rows) use ($callback): void{
            $callback(array_map(fn(array $row) => ClanApplication::fromRow($row), $rows));
        });
    }

    /** @param callable(bool,string):void $callback */
    public function acceptApplication(Session $actingSession, string $applicationId, callable $callback): void{
        $provider = $this->plugin->getProvider();

        $provider->getClanApplicationById($applicationId, function(?array $row) use ($provider, $actingSession, $callback): void{
            if($row === null || $row["status"] !== "pending"){
                $callback(false, "That application is no longer pending.");
                return;
            }
            $application = ClanApplication::fromRow($row);
            $clan = $this->getById($application->clanId);
            if($clan === null){
                $callback(false, "That clan no longer exists.");
                return;
            }
            if(!ClanRole::canReviewApplications((string) ($clan->getRole($actingSession->getPlayer()->getName()) ?? ""))){
                $callback(false, "You don't have permission to review applications.");
                return;
            }
            if($clan->isFull($this->config)){
                $callback(false, "Your clan is full.");
                return;
            }
            if($this->isInClan($application->username)){
                $provider->setClanApplicationStatus($application->id, "rejected", "Applicant joined another clan.");
                $callback(false, "That player already joined a clan.");
                return;
            }

            $provider->upsertClanMember($application->username, $clan->getId(), ClanRole::MEMBER);
            $provider->setClanApplicationStatus($application->id, "accepted");
            $clan->setMemberLocal($application->username, ClanRole::MEMBER);
            $this->memberIndex[strtolower($application->username)] = $clan->getId();

            $this->notifyClan($clan, PlayerClanDataManager::NOTIFY_MEMBER_JOIN, $application->username . " joined the clan!");
            $callback(true, "Accepted " . $application->username . " into the clan.");
        });
    }

    public function rejectApplication(string $applicationId, string $reason, callable $callback): void{
        $this->plugin->getProvider()->setClanApplicationStatus($applicationId, "rejected", $reason);
        $callback(true, "Application rejected.");
    }

    /** Direct add by OWNER/OFFICER, used for invite-only clans (and as a quick-add shortcut on public ones). @param callable(bool,string):void $callback */
    public function recruitDirect(Session $actingSession, Clan $clan, string $targetUsername, callable $callback): void{
        $actorRole = $clan->getRole($actingSession->getPlayer()->getName());
        if($actorRole === null || !ClanRole::canManageMembers($actorRole)){
            $callback(false, "You don't have permission to recruit members.");
            return;
        }
        if($clan->isFull($this->config)){
            $callback(false, "Your clan is full.");
            return;
        }
        if($this->isInClan($targetUsername)){
            $callback(false, $targetUsername . " is already in a clan.");
            return;
        }

        $this->playerData->getCooldownUntil($targetUsername, function(int $cooldown) use ($clan, $targetUsername, $callback): void{
            if($cooldown > time()){
                $callback(false, $targetUsername . " is on a clan-join cooldown right now.");
                return;
            }
            $this->plugin->getProvider()->upsertClanMember($targetUsername, $clan->getId(), ClanRole::MEMBER);
            $clan->setMemberLocal($targetUsername, ClanRole::MEMBER);
            $this->memberIndex[strtolower($targetUsername)] = $clan->getId();
            $this->notifyClan($clan, PlayerClanDataManager::NOTIFY_MEMBER_JOIN, $targetUsername . " joined the clan!");
            $callback(true, $targetUsername . " was added to the clan.");
        });
    }

    // ==========================================================================
    // Invites - owner/officer sends a request to a specific player, who has
    // to accept it before they join. Unlike recruitDirect() this never adds
    // the target immediately.
    // ==========================================================================

    /** @param callable(bool,string):void $callback */
    public function inviteMember(Session $actingSession, Clan $clan, string $targetUsername, callable $callback): void{
        $actorName = $actingSession->getPlayer()->getName();
        $actorRole = $clan->getRole($actorName);
        if($actorRole === null || !ClanRole::canManageMembers($actorRole)){
            $callback(false, "You don't have permission to invite members.");
            return;
        }
        if($clan->isFull($this->config)){
            $callback(false, "Your clan is full.");
            return;
        }
        if($this->isInClan($targetUsername)){
            $callback(false, $targetUsername . " is already in a clan.");
            return;
        }

        $targetSession = $this->plugin->getSessionManager()->getByName($targetUsername);
        if($targetSession === null || !$targetSession->getPlayer()->isOnline()){
            $callback(false, $targetUsername . " needs to be online to receive a clan invite.");
            return;
        }

        $key = strtolower($targetUsername);
        $existing = $this->pendingInvites[$key] ?? null;
        if($existing !== null && $existing["expiresAt"] > time()){
            $callback(false, $targetUsername . " already has a pending invite.");
            return;
        }

        $this->playerData->getCooldownUntil($targetUsername, function(int $cooldown) use ($clan, $actorName, $targetUsername, $key, $callback): void{
            if($cooldown > time()){
                $callback(false, $targetUsername . " is on a clan-join cooldown right now.");
                return;
            }

            $this->pendingInvites[$key] = [
                "clanId"    => $clan->getId(),
                "inviter"   => $actorName,
                "expiresAt" => time() + 60,
            ];
            $callback(true, "Invite sent to " . $targetUsername . ". It expires in 60 seconds.");
        });
    }

    /** The live invite for this player, if any, or null if there isn't one or it expired. */
    public function getPendingInvite(string $username): ?array{
        $key = strtolower($username);
        $invite = $this->pendingInvites[$key] ?? null;
        if($invite === null || $invite["expiresAt"] < time()){
            unset($this->pendingInvites[$key]);
            return null;
        }
        return $invite;
    }

    public function hasPendingInvite(string $username): bool{
        return $this->getPendingInvite($username) !== null;
    }

    /** @param callable(bool,string):void $callback */
    public function respondToInvite(Session $targetSession, bool $accept, callable $callback): void{
        $username = $targetSession->getPlayer()->getName();
        $key = strtolower($username);
        $invite = $this->getPendingInvite($username);
        if($invite === null){
            $callback(false, "That invite has expired.");
            return;
        }
        unset($this->pendingInvites[$key]);

        $clan = $this->getById($invite["clanId"]);
        if($clan === null){
            $callback(false, "That clan no longer exists.");
            return;
        }

        if(!$accept){
            $inviterSession = $this->plugin->getSessionManager()->getByName($invite["inviter"]);
            if($inviterSession !== null && $inviterSession->getPlayer()->isOnline()){
                $inviterSession->getPlayer()->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $username . TF::GRAY . " declined the invite to join " . $clan->getColoredName() . TF::GRAY . ".");
            }
            $callback(true, "Invite declined.");
            return;
        }

        if($this->isInClan($username)){ $callback(false, "You're already in a clan."); return; }
        if($clan->isFull($this->config)){ $callback(false, "That clan is full now."); return; }

        $this->playerData->getCooldownUntil($username, function(int $cooldown) use ($clan, $username, $invite, $callback): void{
            if($cooldown > time()){
                $callback(false, "You can't join a new clan for another " . $this->formatDuration($cooldown - time()) . ".");
                return;
            }

            $this->plugin->getProvider()->upsertClanMember($username, $clan->getId(), ClanRole::MEMBER);
            $clan->setMemberLocal($username, ClanRole::MEMBER);
            $this->memberIndex[strtolower($username)] = $clan->getId();
            $this->notifyClan($clan, PlayerClanDataManager::NOTIFY_MEMBER_JOIN, $username . " joined the clan!");

            $inviterSession = $this->plugin->getSessionManager()->getByName($invite["inviter"]);
            if($inviterSession !== null && $inviterSession->getPlayer()->isOnline()){
                $inviterSession->getPlayer()->sendMessage(TF::GOLD . "[Clan] " . TF::RESET . $username . TF::GRAY . " accepted your invite and joined the clan!");
            }
            $callback(true, "You joined " . $clan->getColoredName() . TF::RESET . "!");
        });
    }

    // ==========================================================================
    // Leaving / removal
    // ==========================================================================

    /** @param callable(bool,string):void $callback */
    public function leaveClan(Session $session, callable $callback): void{
        $username = $session->getPlayer()->getName();
        $clan = $this->getClanOf($username);
        if($clan === null){ $callback(false, "You're not in a clan."); return; }

        if($clan->isOwner($username) && $clan->getMemberCount() > 1){
            $successor = $this->pickSuccessor($clan, $username);
            $this->transferOwnershipInternal($clan, $successor);
        }

        $this->removeMemberInternal($clan, $username, "Left");

        if($clan->getMemberCount() === 0){
            $this->disbandInternal($clan);
            $callback(true, "You left " . $clan->getName() . ". It had no members left, so it was disbanded.");
            return;
        }

        $callback(true, "You left " . $clan->getName() . ".");
    }

    /** @param callable(bool,string):void $callback */
    public function kickMember(Session $actingSession, string $targetUsername, callable $callback): void{
        $actorName = $actingSession->getPlayer()->getName();
        $clan = $this->getClanOf($actorName);
        if($clan === null){ $callback(false, "You're not in a clan."); return; }

        $actorRole = $clan->getRole($actorName);
        $targetRole = $clan->getRole($targetUsername);

        if($targetRole === null){ $callback(false, "That player isn't in your clan."); return; }
        if($actorRole === null || !ClanRole::canManageMembers($actorRole)){ $callback(false, "You don't have permission to kick members."); return; }
        if($targetRole === ClanRole::OWNER){ $callback(false, "You can't kick the clan owner."); return; }
        if($actorRole === ClanRole::OFFICER && $targetRole === ClanRole::OFFICER){ $callback(false, "Officers can't kick other officers."); return; }
        if(strtolower($targetUsername) === strtolower($actorName)){ $callback(false, "Use \"Leave Clan\" instead."); return; }

        $this->removeMemberInternal($clan, $targetUsername, "Kicked");
        $this->notifyClan($clan, PlayerClanDataManager::NOTIFY_MEMBER_LEAVE, $targetUsername . " was removed from the clan.");
        $callback(true, $targetUsername . " was kicked from the clan.");
    }

    private function removeMemberInternal(Clan $clan, string $username, string $method): void{
        $role = $clan->getRole($username);
        $joinedAt = time();
        // Best-effort: the roster doesn't carry join time in the local
        // cache today, so we timestamp the leave itself; the DB roster
        // still has the authoritative joined_at until this delete lands.

        $this->plugin->getProvider()->deleteClanMember($username);
        $clan->removeMemberLocal($username);
        unset($this->memberIndex[strtolower($username)]);

        $this->playerData->pushHistory($username, [
            "name"      => $clan->getName(),
            "tag"       => $clan->getTag(),
            "joined_at" => $clan->getCreatedAt(),
            "left_at"   => time(),
            "method"    => $method,
        ]);
        $this->playerData->setCooldownUntil($username, time() + $this->config->getJoinCooldownSeconds());
    }

    private function pickSuccessor(Clan $clan, string $excludeUsername): string{
        $exclude = strtolower($excludeUsername);
        $officers = [];
        $members = [];
        foreach($clan->getMembers() as $username => $role){
            if($username === $exclude) continue;
            if($role === ClanRole::OFFICER){ $officers[] = $username; }
            elseif($role === ClanRole::MEMBER){ $members[] = $username; }
        }
        return $officers[0] ?? ($members[0] ?? $excludeUsername);
    }

    private function transferOwnershipInternal(Clan $clan, string $newOwnerUsername): void{
        $this->plugin->getProvider()->updateClanOwner($clan->getId(), $newOwnerUsername);
        $this->plugin->getProvider()->setClanMemberRole($newOwnerUsername, ClanRole::OWNER);
        $clan->setOwnerLocal($newOwnerUsername);
        $clan->setMemberLocal($newOwnerUsername, ClanRole::OWNER);
    }

    /** @param callable(bool,string):void $callback */
    public function transferOwnership(Session $ownerSession, string $targetUsername, callable $callback): void{
        $ownerName = $ownerSession->getPlayer()->getName();
        $clan = $this->getClanOf($ownerName);
        if($clan === null || !$clan->isOwner($ownerName)){ $callback(false, "Only the clan owner can transfer ownership."); return; }
        if(!$clan->hasMember($targetUsername)){ $callback(false, "That player isn't in your clan."); return; }

        $this->transferOwnershipInternal($clan, $targetUsername);
        $this->plugin->getProvider()->setClanMemberRole($ownerName, ClanRole::OFFICER);
        $clan->setMemberLocal($ownerName, ClanRole::OFFICER);
        $callback(true, "Ownership transferred to " . $targetUsername . ".");
    }

    /** @param callable(bool,string):void $callback */
    public function setOfficerRole(Session $ownerSession, string $targetUsername, bool $promote, callable $callback): void{
        $ownerName = $ownerSession->getPlayer()->getName();
        $clan = $this->getClanOf($ownerName);
        if($clan === null || !$clan->isOwner($ownerName)){ $callback(false, "Only the clan owner can manage ranks."); return; }
        $targetRole = $clan->getRole($targetUsername);
        if($targetRole === null || $targetRole === ClanRole::OWNER){ $callback(false, "Invalid target."); return; }

        $newRole = $promote ? ClanRole::OFFICER : ClanRole::MEMBER;
        $this->plugin->getProvider()->setClanMemberRole($targetUsername, $newRole);
        $clan->setMemberLocal($targetUsername, $newRole);
        $callback(true, $targetUsername . " is now " . ClanRole::displayName($newRole) . ".");
    }

    /** @param callable(bool,string):void $callback */
    public function disbandClan(Session $ownerSession, callable $callback): void{
        $ownerName = $ownerSession->getPlayer()->getName();
        $clan = $this->getClanOf($ownerName);
        if($clan === null || !$clan->isOwner($ownerName)){ $callback(false, "Only the clan owner can disband the clan."); return; }

        foreach(array_keys($clan->getMembers()) as $username){
            if(strtolower($username) !== strtolower($ownerName)){
                $this->playerData->pushHistory($username, [
                    "name" => $clan->getName(), "tag" => $clan->getTag(),
                    "joined_at" => $clan->getCreatedAt(), "left_at" => time(), "method" => "Disbanded",
                ]);

                $memberSession = $this->plugin->getSessionManager()->getByName($username);
                if($memberSession !== null && $memberSession->getPlayer()->isOnline()){
                    $memberSession->getPlayer()->sendMessage(TF::GOLD . "[Clan] " . TF::RED . $clan->getColoredName() . TF::RED . " was disbanded by the owner.");
                }
            }
        }

        $this->disbandInternal($clan);
        $callback(true, $clan->getName() . " has been disbanded.");
    }

    /**
     * Staff/OP variant of disbandClan() - doesn't require the caller to be
     * a member (let alone the owner) of the clan being removed, since an
     * admin acting on `/clan disband <name>` almost never is. Looks the
     * clan up by name and notifies every online member the same way the
     * owner-initiated flow does.
     */
    public function adminDisbandClan(string $clanName, callable $callback): void{
        $clan = $this->getByName($clanName);
        if($clan === null){ $callback(false, "No clan named \"" . $clanName . "\" was found."); return; }

        foreach(array_keys($clan->getMembers()) as $username){
            $this->playerData->pushHistory($username, [
                "name" => $clan->getName(), "tag" => $clan->getTag(),
                "joined_at" => $clan->getCreatedAt(), "left_at" => time(), "method" => "Disbanded by staff",
            ]);

            $memberSession = $this->plugin->getSessionManager()->getByName($username);
            if($memberSession !== null && $memberSession->getPlayer()->isOnline()){
                $memberSession->getPlayer()->sendMessage(TF::GOLD . "[Clan] " . TF::RED . $clan->getColoredName() . TF::RED . " was disbanded by staff.");
            }
        }

        $name = $clan->getName();
        $this->disbandInternal($clan);
        $callback(true, $name . " has been disbanded.");
    }

    private function disbandInternal(Clan $clan): void{
        foreach(array_keys($clan->getMembers()) as $username){
            $this->plugin->getProvider()->deleteClanMember($username);
            unset($this->memberIndex[strtolower($username)]);
        }
        $this->plugin->getProvider()->deleteClan($clan->getId());
        unset($this->clans[$clan->getId()]);
    }

    // ==========================================================================
    // Settings
    // ==========================================================================

    /** @param callable(bool,string):void $callback */
    public function updateSettings(Session $ownerSession, array $draft, callable $callback): void{
        $ownerName = $ownerSession->getPlayer()->getName();
        $clan = $this->getClanOf($ownerName);
        if($clan === null || !ClanRole::canManageSettings((string) ($clan->getRole($ownerName) ?? ""))){
            $callback(false, "You don't have permission to edit clan settings.");
            return;
        }

        $name = trim((string) $draft["name"]);
        $tag = trim((string) $draft["tag"]);
        $description = trim((string) $draft["description"]);
        $crestId = (string) $draft["crest_id"];
        $color = (string) $draft["color"];
        $visibility = (string) $draft["visibility"];
        $requiredLevel = (int) $draft["required_level"];

        if(strtolower($name) !== strtolower($clan->getName()) && ($error = $this->validateName($name)) !== null){ $callback(false, $error); return; }
        if(strtolower($tag) !== strtolower($clan->getTag()) && ($error = $this->validateTag($tag)) !== null){ $callback(false, $error); return; }
        if(($error = $this->validateDescription($description)) !== null){ $callback(false, $error); return; }
        if(!ClanCrestRegistry::exists($crestId)){ $callback(false, "Invalid crest selection."); return; }

        $this->plugin->getProvider()->updateClanMeta($clan->getId(), [
            "name" => $name, "tag" => $tag, "color" => $color, "description" => $description,
            "crest_id" => $crestId, "visibility" => $visibility, "required_level" => $requiredLevel,
        ]);
        $clan->setMetaLocal($name, $tag, $color, $description, $crestId, $visibility, $requiredLevel);
        $callback(true, "Clan settings updated.");
    }

    // ==========================================================================
    // Bank
    // ==========================================================================

    /** @param callable(bool,string):void $callback */
    public function depositBank(Session $session, int $amount, callable $callback): void{
        $username = $session->getPlayer()->getName();
        $clan = $this->getClanOf($username);
        if($clan === null){ $callback(false, "You're not in a clan."); return; }
        if($amount <= 0){ $callback(false, "Enter a positive amount."); return; }
        if($session->getCoins() < $amount){ $callback(false, "You don't have that many coins."); return; }

        $limit = $this->config->getDailyBankDepositLimit();
        $since = strtotime("today");
        $this->plugin->getProvider()->getClanDailyDepositTotal($clan->getId(), $username, $since, function(int $depositedToday) use ($session, $clan, $amount, $limit, $username, $callback): void{
            if($depositedToday + $amount > $limit){
                $remaining = max(0, $limit - $depositedToday);
                $callback(false, "Daily deposit limit reached. You can deposit " . $remaining . " more coins today.");
                return;
            }

            $session->setCoins($session->getCoins() - $amount);
            $this->plugin->getProvider()->addClanBank($clan->getId(), $amount);
            $this->plugin->getProvider()->addClanBankLog($clan->getId(), $username, "deposit", $amount);
            $clan->addBankLocal($amount);
            $callback(true, "Deposited " . $amount . " coins into the clan bank.");
        });
    }

    /**
     * Withdraws coins from the clan bank back to the acting officer/owner's
     * own balance. This is a safe, generic release valve until dedicated
     * spend targets (capacity boosts, clan-exclusive cosmetics, temporary XP
     * boosts) ship in a later phase - see the spec's own "Coming Soon" note
     * on Clan Wars for the same reasoning.
     * @param callable(bool,string):void $callback
     */
    public function withdrawBank(Session $session, int $amount, callable $callback): void{
        $username = $session->getPlayer()->getName();
        $clan = $this->getClanOf($username);
        if($clan === null){ $callback(false, "You're not in a clan."); return; }
        $role = $clan->getRole($username);
        if($role === null || !ClanRole::canManageBank($role)){ $callback(false, "You don't have permission to withdraw from the bank."); return; }
        if($amount <= 0){ $callback(false, "Enter a positive amount."); return; }

        $this->plugin->getProvider()->withdrawClanBank($clan->getId(), $amount, function(bool $ok) use ($session, $clan, $amount, $username, $callback): void{
            if(!$ok){
                $callback(false, "The clan bank doesn't have that much.");
                return;
            }
            $session->addCoins($amount);
            $this->plugin->getProvider()->addClanBankLog($clan->getId(), $username, "withdraw", $amount);
            $clan->addBankLocal(-$amount);
            $callback(true, "Withdrew " . $amount . " coins from the clan bank.");
        });
    }

    public function getBankHistory(Clan $clan, callable $callback): void{
        $this->plugin->getProvider()->getClanBankLogRecent($clan->getId(), 30, $callback);
    }

    // ==========================================================================
    // Chat / notifications
    // ==========================================================================

    /** Messages every online member whose notification toggle for $category is enabled. */
    public function notifyClan(Clan $clan, string $category, string $message): void{
        foreach(array_keys($clan->getMembers()) as $username){
            $memberSession = $this->plugin->getSessionManager()->getByName($username);
            if($memberSession === null || !$memberSession->getPlayer()->isOnline()) continue;

            $this->playerData->isNotificationEnabled($username, $category, function(bool $enabled) use ($memberSession, $clan, $message): void{
                if($enabled && $memberSession->getPlayer()->isOnline()){
                    $memberSession->getPlayer()->sendMessage($clan->getColoredTag() . " §f" . $message);
                }
            });
        }
    }

    // ==========================================================================
    // Weekly leaderboard reset
    // ==========================================================================

    public function performWeeklyReset(): void{
        $clans = $this->getAll();
        usort($clans, fn(Clan $a, Clan $b) => $b->computeWeeklyScore($this->config) <=> $a->computeWeeklyScore($this->config));

        $rank = 0;
        foreach($clans as $clan){
            $rank++;
            $score = $clan->computeWeeklyScore($this->config);
            $bestRank = ($clan->getBestWeekRank() === 0 || $rank < $clan->getBestWeekRank()) ? $rank : $clan->getBestWeekRank();

            $this->plugin->getProvider()->setClanLastWeek($clan->getId(), $score, $rank, $bestRank);
            $this->plugin->getProvider()->resetClanWeekly($clan->getId());
            $clan->setLastWeekLocal($score, $rank, $bestRank);
            $clan->resetWeeklyLocal();

            $this->notifyClan($clan, PlayerClanDataManager::NOTIFY_WEEKLY_RESULT, "Weekly results are in - your clan finished rank #" . $rank . " with a score of " . $score . ".");
        }
    }

    private function formatDuration(int $seconds): string{
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        if($hours > 0){
            return $hours . "h " . $minutes . "m";
        }
        return max(1, $minutes) . "m";
    }
}
