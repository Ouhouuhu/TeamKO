<?php

namespace ouhouuhu;

use Exception;
use FML\Controls\Labels\Label_Button;
use ManiaControl\Admin\AuthenticationManager;
use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Quad;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\TrackMania\OnScoresStructure;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Commands\CommandListener;
use Maniaplanet\DedicatedServer\InvalidArgumentException;
use MatchManagerSuite\MatchManagerCore;
use ouhouuhu\Classes\Team;
use ouhouuhu\Classes\ColorLib;
use ouhouuhu\Classes\TeamManager;

/**
 * RMX Teams Widget
 *
 * @author         ouhouuhu based on a script by Beu
 * @license        http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class PluginTeamKO implements CommandListener, CallbackListener, Plugin
{

//<editor-fold defaultstate="collapsed" desc="Constans and Variables">
    /*
     * Constants
     */
    const PLUGIN_ID = 198;
    const PLUGIN_VERSION = 1.1;
    const PLUGIN_NAME = 'PluginTeamKO Plugin';
    const PLUGIN_AUTHOR = 'ouhouuhu';
    const MATCHMANAGERCORE_PLUGIN = 'MatchManagerSuite\\MatchManagerCore';
    const RMXTEAMSWIDGET_KO_CALLBACK = 'Trackmania.Knockout.Elimination';
    // MatchManagerWidget Properties
    const MLID_RMXTEAMSWIDGET_LIVE_WIDGETBACKGROUND = 'RMXTeamsWidget.Background';
    const MLID_RMXTEAMSWIDGET_LIVE_WIDGETDATA = 'RMXTeamsWidget.Data';
    const SETTING_RMXTEAMSWIDGET_SHOWPLAYERS = 'Show for Players';
    const SETTING_RMXTEAMSWIDGET_SHOWSPECTATORS = 'Show for Spectators';
    const SETTING_RMXTEAMSWIDGET_MAX_TEAM_SIZE = 'Maximum number of players per team';
    const SETTING_RMXTEAMSWIDGET_TEAM_NAMES = 'Team names';
    const SETTING_RMXTEAMSWIDGET_TEAM_CHATPREFIXS = 'Team chat prefixes';
    const SETTING_RMXTEAMSWIDGET_TEAM_COLORS = 'Team colors';
    const SETTING_RMXTEAMSWIDGET_FREE_TEAM = 'Free team mode';
    const MLID_TEAMKO_WINDOW = 'TeamKOWidget.Window';
    const MLID_TEAMKO_WIDGET = 'TeamKOWidget.Widget';
    const ACTION_SPECTATE_PLAYER = 'TeamKO.SpectatePlayer';
    const ACTION_CLOSE_WIDGET = 'TeamKO.CloseWidget';
    const ACTION_TEAM = 'TeamKO.ToTeam';
    const ACTION_REMOVE = 'TeamKO.Remove';

    /*
     * Private properties
     */

    /** @var ManiaControl $maniaControl */
    private $maniaControl;

    /** @var MatchManagerCore */
    private $MatchManagerCore;

    /** @var TeamManager */
    private $teamManager;

    /** @var string[] */
    private $playersWithML = [];

    /** @var string[] */
    private $specsWithML = [];

    /** @var bool */
    private $freeTeamMode;

    /** @var integer */
    private $playersKOed = 0;

    /** @var integer */
    private $playersAtStart;

    /** @var string */
    private $chatPrefix = '$s';

    /** @var integer */
    private $matchRoundNb = -1;

    /** @var Team|null */
    private $reviveTeam = null;

    //</editor-fold>
//<editor-fold desc="ManiaControl declares">

    /**
     * @see Plugin::getId()
     */
    public static function getId(): int
    {
        return self::PLUGIN_ID;
    }

    /**
     * @see Plugin::getName()
     */
    public static function getName(): string
    {
        return self::PLUGIN_NAME;
    }

    /**
     * @see Plugin::getVersion()
     */
    public static function getVersion(): float
    {
        return self::PLUGIN_VERSION;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getAuthor()
     */
    public static function getAuthor(): string
    {
        return self::PLUGIN_AUTHOR;
    }

    /**
     * @see Plugin::getDescription()
     */
    public static function getDescription(): string
    {
        return 'Play KO in teams';
    }

    /**
     * @param ManiaControl $maniaControl
     * @see Plugin::prepare()
     */
    public static function prepare(ManiaControl $maniaControl)
    {

    }

    /**
     * @param ManiaControl $maniaControl
     * @return bool
     * @see Plugin::load()
     */
    public function load(ManiaControl $maniaControl): bool
    {
        $this->maniaControl = $maniaControl;
        $this->teamManager = new TeamManager();

        $this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);

        // Callbacks
        $this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTMATCHEND, $this, 'handleStartMatch');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_ENDMATCHEND, $this, 'handleEndMatch');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_SCORES, $this, 'handleEndRoundCallback');
        $this->maniaControl->getCallbackManager()->registerCallbackListener('MatchManager.StartMatch', $this, 'handleMatchManagerStart');
        $this->maniaControl->getCallbackManager()->registerCallbackListener('MatchManager.EndMatch', $this, 'handleMatchManagerEnd');
        $this->maniaControl->getCallbackManager()->registerCallbackListener('MatchManager.StopMatch', $this, 'handleMatchManagerEnd');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleManialinkPageAnswer');
        $this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::RMXTEAMSWIDGET_KO_CALLBACK, $this, 'handleKnockoutCallback');

        // Settings
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_RMXTEAMSWIDGET_MAX_TEAM_SIZE, '3', "Maximum number of players per team that are playing");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_RMXTEAMSWIDGET_SHOWPLAYERS, true, "Display widget for players");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_RMXTEAMSWIDGET_SHOWSPECTATORS, true, "Display widget for spectators");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_RMXTEAMSWIDGET_TEAM_NAMES, '$f00Team 1, $0f0Team 2', "Name of the teams");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_RMXTEAMSWIDGET_TEAM_CHATPREFIXS, '$f00T1, $0f0T2', "Team prefixs");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_RMXTEAMSWIDGET_TEAM_COLORS, 'f00, 0f0', "Team Colors");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_RMXTEAMSWIDGET_FREE_TEAM, true, "Tells if teams are made by admins or if players can join the team they want");

        // Commands
        $this->maniaControl->getCommandManager()->registerCommandListener('jointeam', $this, 'cmdJoinTeam', false, 'Allow players to join the team they want');
        $this->maniaControl->getCommandManager()->registerCommandListener('leaveteam', $this, 'cmdLeaveTeam', false, 'Allow players to leave the team');
        $this->maniaControl->getCommandManager()->registerCommandListener('teams', $this, 'cmdGetTeam', true, 'Allow players to see the teams');
        $this->maniaControl->getCommandManager()->registerCommandListener('addteam', $this, 'cmdAddToTeam', true, 'Add players to teams');
        $this->maniaControl->getCommandManager()->registerCommandListener('kickteam', $this, 'cmdKickFromTeam', true, 'Remove players from teams');

        $this->maniaControl->getCommandManager()->registerCommandListener('test', $this, 'test_function', true, 'TEST function, do not use it!');
        $this->maniaControl->getCommandManager()->registerCommandListener('test2', $this, 'test_function2', true, 'TEST function, do not use it!');
        $this->maniaControl->getCommandManager()->registerCommandListener('purgeteams', $this, 'cmdPurgeTeam', true, 'Remove disconnected players');
        $this->maniaControl->getCommandManager()->registerCommandListener('initteams', $this, 'cmdClearTeam', true, 'Init teams and remove players');
        $this->maniaControl->getCommandManager()->registerCommandListener('hidegfx', $this, 'cmdHideGfx', true, 'Hides graphics');
        $this->maniaControl->getCallbackManager()->registerCallbackListener('ManiaPlanet.PlayerChat', $this, 'handlePlayerChat');

        $this->initTeams();
        $this->displayManialinks(false);

        try {
            $this->maniaControl->getClient()->chatEnableManualRouting();
        } catch (Exception $ex) {
            $this->maniaControl->getChat()->sendErrorToAdmins($this->chatPrefix . $ex->getMessage());
        }

        return true;
    }

    /**
     * @throws InvalidArgumentException
     * @see Plugin::unload()
     */
    public function unload()
    {
        $this->closeWidgets();
        $this->maniaControl->getClient()->chatEnableManualRouting(false);
        $this->maniaControl->getCallbackManager()->unregisterCallbackListening('ManiaPlanet.OnPlayerChat', $this);
    }

//</editor-fold>
//<editor-fold desc="Helper functions">

    private function purgeTeams()
    {
        foreach ($this->teamManager->getTeams() as $team) {
            foreach ($team->getPlayers() as $player) {
                if (!$player->isConnected)
                    $team->removePlayerByLogin($player->login);
            }
        }
    }

    private function revivePlayer()
    {
        if ($this->reviveTeam !== null) {
            try {
                $login = $this->reviveTeam->getReviveLogin();
                if ($login === null) {
                    Logger::logWarning("Tried to revive player, but was unable to find login!");
                    return;
                }

                $playerOnServer = false;
                //check if the player is on the server for optimization we could use the TeamManager, so we don't need to loop on all players
                foreach ($this->maniaControl->getPlayerManager()->getPlayers() as $player) {
                    if ($player->login == $login) {
                        $playerOnServer = true;
                        break;
                    }
                }

                if ($playerOnServer) {
                    $this->maniaControl->getClient()->TriggerModeScriptEvent("Knockout.Revive", [$login]);
                    $player = $this->reviveTeam->getPlayer($login);
                    $player->isAlive = true;
                    $this->playersKOed -= 1;
                    Logger::logInfo("Revived " . $player->player->getEscapedNickname());
                    $this->displayReviveNotification($login);
                } else {
                    $this->maniaControl->getChat()->sendError($this->chatPrefix . 'The player to revive is not anymore on the server!');
                    $this->reviveTeam->getPlayer($login)->isConnected = false;
                }
            } catch (InvalidArgumentException $e) {
                Logger::logError($e->getMessage());
            }
        }
    }

    private function checkEndMatch()
    {
        $teamsAlive = [];
        $playersAlive = 0;
        $stopMatch = false;

        foreach ($this->teamManager->getTeams() as $team) {
            $playersAlive += $team->getAliveAmount();
            if ($team->getAliveAmount() > 0) {
                $teamsAlive[] = $team;
            }
        }

        if (count($teamsAlive) <= 1) {
            $stopMatch = True;
        }

        if ($playersAlive == 1 || $stopMatch) {
            Logger::logInfo("Force Match to End");
            $this->matchRoundNb = -1; // reset matchRoundNumber, so we don't get revives
            if (count($teamsAlive) == 1) {
                $winningTeam = $teamsAlive[0];
                $this->displayWinningTeam($winningTeam);
                $this->maniaControl->getChat()->sendSuccess('$z$sTeam $o' . $winningTeam->teamName . '$z$s wins the match!');
            }
            $this->MatchManagerCore->MatchStop(); //we should use MatchEnd() but it's not working for some reasons with MatchEnd
        }
        $this->checkPlayerStatuses();
        $this->displayManialinks(true);
    }

    private function initTeams()
    {
        $this->teamManager->setTeamSize($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_MAX_TEAM_SIZE));
        $this->freeTeamMode = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_FREE_TEAM);

        $teamNameStr = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_TEAM_NAMES);
        $teamPrefixStr = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_TEAM_CHATPREFIXS);
        $teamColorStr = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_TEAM_COLORS);

        $teamNames = [];
        $teamPrefixes = [];
        $teamColors = [];

        if (strlen($teamNameStr) > 0) {
            $teamNames = explode(',', str_replace(' ', '', $teamNameStr));
        }
        if (strlen($teamPrefixStr) > 0) {
            $teamPrefixes = explode(',', str_replace(' ', '', $teamPrefixStr));
        }
        if (strlen($teamColorStr) > 0) {
            $teamColors = explode(',', str_replace(' ', '', $teamColorStr));
        }

        if (count($teamNames) != count($teamPrefixes)) {
            $this->maniaControl->getChat()->sendError('There is an error in team number!');
            return;
        }
        if (count($teamNames) != count($teamColors)) {
            $this->maniaControl->getChat()->sendError('There is an error in team number!');
            return;
        }

        if (count($teamNames) > 4) {
            $this->maniaControl->getChat()->sendError('There are too many teams, UI might not be optimal!');
        }

        $this->teamManager->removeTeams();

        foreach ($teamNames as $i => $teamName) {
            $this->teamManager->addTeam($teamName, $teamPrefixes[$i], str_replace('$', '', $teamColors[$i]));
        }
    }

    /**
     * @param string $accountId
     * @return string
     */
    private function getLoginFromAccountID(string $accountId): string
    {
        $accountId = str_replace("-", "", $accountId);
        $login = "";
        foreach (str_split($accountId, 2) as $pair) {
            $login .= chr(hexdec($pair));
        }
        $login = base64_encode($login);
        $login = str_replace("+", "-", str_replace("/", "_", $login));
        return trim($login, "=");
    }

    /**
     * @param string $login
     * @return string
     */
    private function getAccountIDFromLogin(string $login): string
    {
        $login = str_pad($login, 24, "=", STR_PAD_RIGHT);
        $login = str_replace("_", "/", str_replace("-", "+", $login));
        $login = base64_decode($login);
        return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($login), 4));
    }

    /**
     * @param string|null $login
     * @param int $team
     * @return void
     */
    private function addPlayerToTeam(?string $login, int $team)
    {
        if ($login === null) {
            return;
        }

        $player = $this->maniaControl->getPlayerManager()->getPlayer($login, true);
        if ($player === null) {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . "Can't find player at server, disconnected?");
            return;
        }

        $matchStarted = $this->MatchManagerCore->getMatchStatus();
        if ($matchStarted) {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . "Can't add player to team $team, match is already started.");
            return;
        }

        $koPlayer = $this->teamManager->addPlayerToTeam($player, $team - 1);
        if ($koPlayer === null) {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . "Unable to add player " . $player->getEscapedNickname() . " to team " . $team);
        }

        $this->displayManialinks(false);
    }

    private function removePlayerFromTeam(string $login)
    {
        $matchStarted = $this->MatchManagerCore->getMatchStatus();
        if ($matchStarted) {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . " Can't remove player from team, match is already started.");
            return;
        }
        foreach ($this->teamManager->getTeams() as $team) {
            $team->removePlayerByLogin($login);
        }
    }

    /** format number */
    private function fn(string $number)
    {
        if (hexdec($number) < 10) return "0" . $number;
        return $number;
    }

    /**
     * Update Widgets on Setting Changes
     *
     * @param Setting $setting
     */
    public function updateSettings(Setting $setting)
    {
        if ($setting->belongsToClass($this)) {
            $this->closeWidgets();
            $this->initTeams();
            $this->teamManager->resetPlayerStatuses();
            $this->displayManialinks(false);
        }
    }

    private function checkPlayerStatuses()
    {
        $players = $this->maniaControl->getPlayerManager()->getPlayers();

        foreach ($this->teamManager->getTeams() as $team) {
            foreach ($team->getPlayerLogins() as $login) {
                $teamPlayer = $team->getPlayer($login);
                if ($teamPlayer) {
                    $teamPlayer->isConnected = false;
                    if (isset($players[$login])) {
                        $teamPlayer->isConnected = true;
                        Logger::logInfo($teamPlayer->player->getEscapedNickname() . " connects");
                    }
                }
            }
        }
    }

    /**
     * Reset players alive status at Match Start
     */
    public function test_function($callback, Player $cbPlayer)
    {
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($cbPlayer, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($cbPlayer);
            return;
        }

        $this->teamManager->addPlayerToTeam($cbPlayer, 0);

        $teamNb = sizeof($this->teamManager->getTeams());

        for ($i = 0; $i < $this->teamManager->getTeamSize(); $i++) {
            $login = "*fakeplayer" . ($i + 1) . "*";
            $team = $this->teamManager->getTeam($i % $teamNb);
            if ($team !== null) {
                $player = $this->maniaControl->getPlayerManager()->getPlayer($login, true);
                if ($player) $team->addPlayer($player);
            }
        }

        $this->MatchManagerCore->onCommandMatchStart([], $cbPlayer);
    }

    public function test_function2($callback, Player $cbPlayer)
    {
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($cbPlayer, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($cbPlayer);
            return;
        }

        for ($i = 1; $i <= 9; $i++) {//put same number than above to end the loop for proper testing
            $this->maniaControl->getClient()->connectFakePlayer();
        }
    }

    /**
     * @param ?Team $team
     * @return void
     */
    private function displayWinningTeam(?Team $team)
    {
        $teamName = "unknown";
        if ($team) {
            $teamName = addslashes($team->teamName);
        }
        $manialink = /** @lang text */
            <<<EOT
 <manialink version="3" id="teamKO.Winner">
    <frame pos="0 10" z-index="201">
    <label id="winner" pos="0 -9" z-index="3" size="170 25" text="$teamName" halign="center" valign="center2" textsize="28" textfont="GameFontSemiBold" opacity="0.9" textemboss="1"/>
    <label id="title" pos="0 25" opacity="0.9" z-index="2" size="170 12" text="to the winner of the match" halign="center" valign="center2" textfont="GameFontSemiBold" textsize="8"/>
    <label pos="0 37" z-index="2" size="258 45.8" text="\$tCongratulations!" halign="center" valign="center2" textfont="GameFontBlack" textsize="20" opacity="0.9" hidden=""/>
    
    <quad pos="109 0" z-index="0" size="216 80.4" opacity="0.6" halign="center" valign="center" image="file://Media/Manialinks/Nadeo/TMNext/Menus/PageProfile/UI_profile_background_map_lights.dds"  scale="2" hidden="1"/>
    </frame>

    <quad z-index="200" image="file://Media/Manialinks/Nadeo/TMNext/Menus/PageMatchmakingMain/Background/B_Night.dds" pos="52.1 -28.3" halign="center" valign="center2" size="360 180"  modulatecolor="" keepratio="Fit" scale="2"/>
 
    <script><!--
    #Include "MathLib" as ML
    
    main() {
        declare CMlLabel winner <=> (Page.GetFirstChild("winner") as CMlLabel);
        while(True) {
                yield;
                winner.RelativeScale = (1 + ML::Sin(Now*1.0 / 500) / 8);
                winner.RelativeRotation = (ML::Sin(Now*1.0 / 500) / 4) * 20.;
      }
        
    }
    --></script>
</manialink>
EOT;

        $this->maniaControl->getManialinkManager()->sendManialink($manialink);
    }

    private function displayReviveNotification($login): void
    {

        if ($this->reviveTeam === null || $login === null) return;

        $nick = addslashes($this->reviveTeam->getPlayer($login)->player->getEscapedNickname());
        $team = addslashes($this->reviveTeam->teamName);

        $manialink = /** @lang text */
            <<<EOT
    <manialink version="3" id="teamKO.revive">
        <frame pos="0 -5">
            <frame size="100 12" pos="0 0" valign="center" halign="center" id="frameRevive1">
                <label pos="0 -12" z-index="0" size="100 12" text="PLAYER" textfont="GameFontSemiBold" halign="center" valign="center2" textsize="12"/>
            </frame>
            <frame size="100 12" pos="0 -12" valign="center" halign="center" id="frameRevive2">
                <label pos="0 12" z-index="1" size="100 12" text="R E V I V E S" textfont="GameFontBlack" halign="center" valign="center2" textsize="12"/>
                <quad pos="0 12" z-Index="0" size="102 12" bgcolor="00D27BFF" opacity="1" valign="center" halign="center"/>
            </frame>
            <frame size="100 12" pos="0 0" valign="center" halign="center" id="framePlayer1">
                <label pos="0 -12" size="100 12" halign="center" valign="center2" textfont="GameFontBlack" text="$nick" textemboss="1" textsize="12" textprefix="\$t" opacity="1"/>
            </frame>
                <frame size="100 12" pos="0 -8" valign="center" halign="center" id="framePlayer2">
                <label pos="0 12" size="100 7" halign="center" valign="center2" textfont="GameFontBlack" text="$team" textemboss="1" textsize="4" opacity="1"/>
            </frame>
        </frame>
    
    <script><!-- 
    
    main() {
        declare CMlFrame frame1 = (Page.GetFirstChild("frameRevive1") as CMlFrame);
        declare CMlFrame frame2 = (Page.GetFirstChild("frameRevive2") as CMlFrame);
        declare CMlFrame frame3 = (Page.GetFirstChild("framePlayer1") as CMlFrame);
        declare CMlFrame frame4= (Page.GetFirstChild("framePlayer2") as CMlFrame);
        
        AnimMgr.Add(frame1.Controls[0], """<elem pos="0 0"/>""", Now, 1000, CAnimManager::EAnimManagerEasing::ElasticOut);
	    AnimMgr.Add(frame2.Controls[0], """<elem pos="0 -1"/>""", Now, 1000, CAnimManager::EAnimManagerEasing::ElasticOut);
	    AnimMgr.Add(frame2.Controls[1], """<elem pos="0 0"/>""", Now, 1000, CAnimManager::EAnimManagerEasing::ElasticOut);

        AnimMgr.Add(frame1.Controls[0], """<elem pos="0 -12"/>""", Now+1500, 1000, CAnimManager::EAnimManagerEasing::ElasticOut);
        AnimMgr.Add(frame2.Controls[0], """<elem pos="0 12"/>""", Now+1500, 1000, CAnimManager::EAnimManagerEasing::ElasticOut);
        AnimMgr.Add(frame2.Controls[1], """<elem pos="0 12"/>""", Now+1500, 1000, CAnimManager::EAnimManagerEasing::ElasticOut);
        
        AnimMgr.Add(frame3.Controls[0], """<elem pos="0 0"/>""", Now+2000, 1500, CAnimManager::EAnimManagerEasing::ElasticOut);
        AnimMgr.Add(frame4.Controls[0], """<elem pos="0 0"/>""", Now+2000, 1500, CAnimManager::EAnimManagerEasing::ElasticOut);
    }
    --></script>
    </manialink>
EOT;

        $this->maniaControl->getManialinkManager()->sendManialink($manialink, null, 5000);

    }
    //</editor-fold>
    //<editor-fold desc="Callbacks">

    /**
     * Called on ManialinkPageAnswer
     *
     * @param array $callback
     */
    public function handleManialinkPageAnswer(array $callback)
    {
        $actionId = $callback[1][2];
        $actionArray = explode('.', $actionId);
        if (count($actionArray) < 2) {
            return;
        }
        $action = $actionArray[0] . '.' . $actionArray[1];

        if (count($actionArray) > 2) {

            $login = $callback[1][1];
            $targetLogin = $actionArray[2];
            switch ($action) {

                case self::ACTION_CLOSE_WIDGET:
                    $this->closeTeamWindow($login);
                    break;
                case self::ACTION_TEAM:
                    $this->addPlayerToTeam($targetLogin, ($actionArray[3] + 1));
                    $this->showTeamWindow($login);
                    break;
                case self::ACTION_REMOVE:
                    $this->removePlayerFromTeam($targetLogin);
                    $this->displayManialinks(false);
                    $this->showTeamWindow($login);
                    break;
            }
        }
    }

    /**
     * @param $dummy
     * @return void
     */
    public function handleStartMatch($dummy): void
    {
        $matchStarted = $this->MatchManagerCore->getMatchStatus();
        if ($matchStarted) {
            $this->checkPlayerStatuses();
            $this->displayManialinks(false);
        }
    }

    public function handleMatchManagerStart($matchid, $settings)
    {
        Logger::logInfo("start_match");
        $this->matchRoundNb = -1;
        $this->checkPlayerStatuses();
        //move to spectator players without teams
        foreach ($this->maniaControl->getPlayerManager()->getPlayers(true) as $player) {
            $team = $this->teamManager->getPlayerTeam($player->login);
            if ($team == null) {
                $this->maniaControl->getClient()->forceSpectator($player, 1);
            }
        }
        $this->purgeTeams();
        $this->teamManager->resetPlayerStatuses();
        $this->playersKOed = 0;
        $this->playersAtStart = 0;
        foreach ($this->teamManager->getTeams() as $team) {
            $this->playersAtStart += sizeof($team->getPlayers());
        }

        $this->displayManialinks(false);
    }

    /**
     * @param $dummy
     * @return void
     */
    public function handleMatchManagerEnd($dummy, $settings): void
    {
        Logger::Log("MatchManager -> end_match");
        $this->teamManager->resetPlayerStatuses();
        $this->displayManialinks(false);
    }

    /**
     * @param $dummy
     * @return void
     */
    public function handleEndMatch($dummy): void
    {
        $matchStatus = $this->MatchManagerCore->getMatchStatus();
        if ($matchStatus) $this->matchRoundNb += 1;
    }

    public function handleEndRoundCallback(OnScoresStructure $structure): void
    {
        Logger::logInfo("!" . $structure->getSection());

        if ($structure->getSection() == "EndMap") {
            try {
                $this->maniaControl->getManialinkManager()->hideManialink("teamKO.Winner");
            } catch (Exception $e) {
                Logger::logError($e->getMessage());
            }
            return;
        }

        $matchStatus = $this->MatchManagerCore->getMatchStatus();
        if (!$matchStatus) return;

        if ($structure->getSection() == "EndRound") {
            $this->checkEndMatch();
            return;
        }

        if ($structure->getSection() != "PreEndRound") {
            return;
        }


        Logger::logInfo($structure->getSection());

        $array = $structure->getPlayerScores();
        $first = \array_shift($array);

        if ($first !== null) {
            $this->reviveTeam = $this->teamManager->getPlayerTeam($first->getPlayer()->login);

        }
    }

    public function handlePlayerChat($callback)
    {
        $playerUid = $callback[1][0];
        $login = $callback[1][1];
        $text = $callback[1][2];

        if ($playerUid == 0)
            return;
        if (substr($text, 0, 1) == "/")
            return;

        $player = $this->maniaControl->getPlayerManager()->getPlayer($login);
        if ($player == null)
            return;

        $nick = $player->nickname;
        $team = $this->teamManager->getPlayerTeam($login);
        $prefix = "";
        if ($team)
            $prefix = trim($team->chatPrefix) . " ";

        try {
            $this->maniaControl->getClient()->chatSendServerMessage('[$<' . $prefix . $nick . '$>] ' . $text);
        } catch (Exception $e) {
            echo "error while sending chat message to $login: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Callback when players are KO
     *
     * @param array $callbackReturn
     */
    public function handleKnockoutCallback(array $callbackReturn): void
    {
        /*$matchStatus = $this->MatchManagerCore->getMatchStatus();
        if ($matchStatus && $this->matchRoundNb < 0)
            return; */

        Logger::log("HandleKnockout");

        $json = json_decode($callbackReturn[1][0], true);

        if (count($json["accountids"]) == 0) { //this means we are in a fake round with any KO
            return;
        }

        $this->revivePlayer();

        foreach ($json["accountids"] as $accountId) {
            if (preg_match("/\*fakeplayer\d+\*/", $accountId)) {
                $login = $accountId;
            } else {
                $login = $this->getLoginFromAccountID($accountId);
            }

            $team = $this->teamManager->getPlayerTeam($login);
            if ($team == null) continue;
            $team->setKnockout($login);
            $this->playersKOed += 1;
        }

    }

    /**
     * Handle PlayerConnect callback
     *
     * @param Player $player
     * @return void
     */
    public function handlePlayerConnect(Player $player): void
    {
        Logger::Log("handlePlayerConnect");
        $matchStatus = $this->MatchManagerCore->getMatchStatus();

        if ($this->freeTeamMode && !$matchStatus) {
            $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "Use /jointeam *number* to join the team you want!", $player);
        }

        $team = $this->teamManager->getPlayerTeam($player->login);
        if ($team === null) {
            if ($matchStatus) {
                try {
                    $this->maniaControl->getClient()->forceSpectator($player, 1);
                } catch (Exception $ex) {
                    Logger::logError($ex->getMessage());
                }
            }
        }

        $player = $team->getPlayer($player->login);
        if ($player !== null) {
            $player->isConnected = true;
        }

        $this->displayManialinks(false);
    }

    /**
     * @param Player $player
     * @return void
     */
    public function handlePlayerDisconnect(Player $player): void
    {
        Logger::Log("handlePlayerDisconnect");
        $team = $this->teamManager->getPlayerTeam($player->login);
        if ($team === null)
            return;
        $player = $team->getPlayer($player->login);
        if ($player === null)
            return;
        $player->isConnected = false;

        $this->displayManialinks(false);
    }

    //</editor-fold>
    //<editor-fold desc="Chat commands">

    public function cmdKickFromTeam(array $chatCallback, Player $player)
    {


        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
            return;
        }

        Logger::Log("add_to_team");
        $text = $chatCallback[1][2];
        $text = explode(" ", $text);

        if (count($text) < 1) {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . "Error with the command, you have to use //addteam *number* *players with , to seperate them*!", $player);
            return;
        }

        unset($text[0]); //removing command

        $players = [];
        $players_str = implode("", $text);
        if (strlen($players_str) > 0) {
            $players = explode(',', str_replace(' ', '', $players_str));
        }

        foreach ($players as $login) {
            $player = $this->maniaControl->getPlayerManager()->getPlayer($login);
            $team = $this->teamManager->getPlayerTeam($login);
            if ($team === null) {
                $this->maniaControl->getChat()->sendError($this->chatPrefix . "Player " . $login . " is not member of any team.", $player);
                return;
            }

            $team->removePlayerByLogin($login);
            $nick = $login;

            if ($player) {
                $nick = $player->getEscapedNickname();
            }

            $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "Player " . $nick . " removed from team " . $team->teamName);
        }

    }

    public function cmdHideGfx(array $chatCallback, Player $player)
    {
        $this->maniaControl->getManialinkManager()->hideManialink("teamKO.Winner");
    }

    /**
     * @param array $chatCallback
     * @param Player $player
     * @return void
     */
    public function cmdAddToTeam(array $chatCallback, Player $player)
    {

        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
            return;
        }

        Logger::Log("add_to_team");
        $text = $chatCallback[1][2];
        $text = explode(" ", $text);
        if (count($text) < 2) {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . "Error with the command, you have to use //addteam *number* *players with , to seperate them*!", $player);
            return;
        }
        if (is_numeric($text[1])) {
            $team = (int)$text[1];
        } else {
            $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "Error with the team!", $player);
            return;
        }
        unset($text[1]);
        unset($text[0]); //removing command and team number

        $players = [];
        $players_str = implode("", $text);
        if (strlen($players_str) > 0) {
            $players = explode(',', str_replace(' ', '', $players_str));
        }

        foreach ($players as $login) {
            $player = $this->maniaControl->getPlayerManager()->getPlayer($login);
            if ($player) {
                if ($this->teamManager->addPlayerToTeam($player, $team - 1) === null) {
                    $this->maniaControl->getChat()->sendError($this->chatPrefix . " Unable to add player " . $player->getEscapedNickname() . "to team " . $team);
                }
            }
        }

        $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "Player(s) were added to the team " . $team . "!", $player);
        $this->displayManialinks(false);
    }

    public function cmdPurgeTeam(array $chatCallback, Player $player)
    {
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
            return;
        }

        $this->purgeTeams();
        $this->teamManager->resetPlayerStatuses();
        $this->displayManialinks(false);
    }

    public function cmdClearTeam(array $chatCallback, Player $player)
    {
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
            return;
        }
        if (!$this->MatchManagerCore->getMatchStatus()) {
            $this->initTeams();
        } else {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . "Can't clear teams, since match is progressing!");
        }

        $this->displayManialinks(false);
    }

    public function cmdLeaveTeam(array $chatCallback, Player $player)
    {
        Logger::Log("leave_team");
        if (!$this->freeTeamMode) {
            $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "You're not allowed to join a team yourself, ask an admin!", $player);
            return;
        }

        if ($this->MatchManagerCore->getMatchStatus()) {
            $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "You can't join a team when a match is running!", $player);
            return;
        }

        $team = $this->teamManager->getPlayerTeam($player->login);
        if ($team === null) {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . "No team to leave from", $player);
            return;
        }

        $team->removePlayer($player);
        $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "Player " . $player->getEscapedNickname() . ' has left $o' . $team->teamName, $player);

        $this->displayManialinks(false);

    }

    public function cmdJoinTeam(array $chatCallback, Player $player)
    {
        Logger::Log("join_team");

        if (!$this->freeTeamMode) {
            $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "You're not allowed to join a team yourself, ask an admin!", $player);
            return;
        }

        if ($this->MatchManagerCore->getMatchStatus()) {
            $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "You can't join a team when a match is running!", $player);
            return;
        }

        $text = explode(" ", $chatCallback[1][2]);
        if (is_numeric($text[1])) {
            $teamNb = (int)$text[1];
        } else {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . "Teams are characterized by numbers!", $player);
            return;
        }

        if ($teamNb > $this->teamManager->getTeamSize() || $teamNb <= 0) {
            $this->maniaControl->getChat()->sendInformation($this->chatPrefix . "The team you're trying to join doesn't exist!", $player);
            return;
        }

        if ($this->teamManager->addPlayerToTeam($player, $teamNb - 1) === null) {
            $this->maniaControl->getChat()->sendError($this->chatPrefix . "Team $teamNb has too many players!", $player);
            return;
        }

        $team = $this->teamManager->getPlayerTeam($player->login);
        $this->maniaControl->getChat()->sendSuccess($this->chatPrefix . "Player " . $player->getEscapedNickname() . ' has joined team $o' . $team->teamName, $player);

        $this->displayManialinks(false);
    }

    public function cmdGetTeam(array $chatCallback, Player $player)
    {
        if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
            $this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
            return;
        }

        $this->showTeamWindow($player->login);
    }

    //</editor-fold>
    //<editor-fold desc="Manialinks">

    /**
     * Close TeamWindow
     *
     * @param string $login
     */
    public function closeTeamWindow(string $login)
    {
        $this->maniaControl->getManialinkManager()->hideManialink(self::MLID_TEAMKO_WINDOW, $login);
    }

    /**
     * Close a Widget
     *
     * @param null|string|string[] $login
     */
    public function closeWidgets($login = null)
    {
        $this->maniaControl->getManialinkManager()->hideManialink(self::MLID_RMXTEAMSWIDGET_LIVE_WIDGETDATA, $login);
        $this->maniaControl->getManialinkManager()->hideManialink(self::MLID_RMXTEAMSWIDGET_LIVE_WIDGETBACKGROUND, $login);
    }

    private function genPlayerInfo($index, $player, $playerFrame, $koQueue)
    {

        if ($koQueue == -1) {
            $koQueue = "";
        } else {
            $koQueue = '$fff ' . $koQueue;
        }

        $label = new Label();
        $label->setText($player->player->getEscapedNickname())
            ->setHorizontalAlign("left")
            ->setVerticalAlign("center")
            ->setTextSize(1.5)
            ->setSize(25, 4)
            ->setTextFont("GameFontRegular")
            ->setPosition(10, -($index * 4 - 0.5), 1);
        $playerFrame->addChild($label);

        $quad = new Quad();
        $quad->setHorizontalAlign("left")
            ->setBackgroundColor("0008")
            ->setSize(25, 3.5)
            ->setPosition(9.5, -($index * 4), 0);
        $playerFrame->addChild($quad);

        $label2 = new Label();
        $label2->setText($player->getStatus() . $koQueue)
            ->setHorizontalAlign("center")
            ->setVerticalAlign("center")
            ->setTextSize(1.5)
            ->setSize(10, 4)
            ->setTextFont("GameFontSemiBold")
            ->setPosition(4, -($index * 4 - 0.5), 1);
        $playerFrame->addChild($label2);

        $quad = new Quad();
        $quad->centerAlign()
            ->setBackgroundColor("0008")
            ->setSize(10, 3.5)
            ->setPosition(4, -($index * 4), 0);
        $playerFrame->addChild($quad);
    }

    /**
     * @param Team $team
     * @return Frame
     */
    private function genTeamFrame(Team $team): Frame
    {

        $players = $team->getPlayers();
        $orderKO = $team->getKnockoutOrder();

        $frame = new Frame();
        $frame->setPosition(0, 0);

        $lbl = new Label();
        $frame->addChild($lbl);
        $lbl->setText($team->teamName)
            ->setPosition(15, 0)
            ->centerAlign()
            ->setTextSize(2)
            ->setTextFont("GameFontBlack");

        $quad = new Quad();
        $frame->addChild($quad);
        $quad->setSize(30, 0.25)
            ->setPosition(15, -2.5)
            ->setBackgroundColor("fff8")
            ->centerAlign();

        $playerFrame = new Frame();
        $frame->addChild($playerFrame);
        $playerFrame->setPosition(-0.5, -6);


        $arrayPlayers = [];
        $arrayKo = [];
        foreach ($players as $player) {
            if (array_key_exists($player->login, $orderKO)) {
                $arrayKo[$orderKO[$player->login]] = $player;
            } else {
                $arrayPlayers[] = $player;
            }
        }
        $index = 0;
        ksort($arrayKo, SORT_NUMERIC);

        foreach ($arrayPlayers as $player) {
            $this->genPlayerInfo($index, $player, $playerFrame, -1);
            $index += 1;
        }

        foreach ($arrayKo as $ko => $player) {
            $this->genPlayerInfo($index, $player, $playerFrame, ($ko + 1));
            $index += 1;
        }

        return $frame;
    }

    /**
     *
     * @param $login
     * @return void
     */
    public function showTeamWidget($login): void
    {
        $maniaLink = new ManiaLink(self::MLID_TEAMKO_WIDGET);

        $frame = new Frame();

        $posCounter = -1;
        foreach ($this->teamManager->getTeams() as $i => $team) {
            $teamFrame = $this->genTeamFrame($team);
            if ($i % 2 == 0) {
                $posCounter += 1;
                $teamFrame->setPosition(-158 + (32 * $posCounter), 75);
            } else {
                $teamFrame->setPosition(128 - (32 * $posCounter), 75);
            }
            $frame->addChild($teamFrame);
        }

        $maniaLink->addChild($frame);

        $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
        Logger::logInfo("Display widget");

    }

    /**
     *
     * @param string $login
     * @return void
     */
    public function showTeamWindow(string $login): void
    {
        $maniaLink = new ManiaLink(self::MLID_TEAMKO_WINDOW);
        $players = [];

        foreach ($this->maniaControl->getPlayerManager()->getPlayers(true) as $player) {
            $players[$player->login] = $player;
        }
        $width = 75;

        foreach ($this->teamManager->getTeams() as $teamIndex => $button) {
            $width += 10;
            foreach ($button->getPlayers() as $player) {
                $players[$player->login] = $player->player;
            }
        }

        $mainFrame = new Frame();
        $mainFrame->setPosition(-45, 65);

        $quad = new Quad();
        $mainFrame->addChild($quad);
        $quad->setSize($width, 120)
            ->setY(5)
            ->setHorizontalAlign("left")
            ->setVerticalAlign("top")
            ->setBackgroundColor("000a");

        $quad2 = new Quad();
        $mainFrame->addChild($quad2);
        $quad2->setSize($width, 5)
            ->setY(5)
            ->setHorizontalAlign("left")
            ->setVerticalAlign("top")
            ->setBackgroundColor("000");

        $label = new Label();
        $mainFrame->addChild($label);
        $label->setText("Players and Teams")
            ->setTextSize(2)
            ->setHorizontalAlign("left")
            ->setPosition(2, 2)
            ->setTextFont("GameFontSemiBold");

        $closeButton = new Label_Button();
        $mainFrame->addChild($closeButton);
        $closeButton->addActionTriggerFeature(self::ACTION_CLOSE_WIDGET . "." . $login);
        $closeButton->setText("X")
            ->centerAlign()
            ->setTextSize(1.5)
            ->setSize(5, 5)
            ->setAreaColor("900")
            ->setAreaFocusColor("f00")
            ->setPosition($width - 2.5, 2.5);


        $teamFrame = new Frame();
        $teamFrame->setPosition(0, -2.5);
        $mainFrame->addChild($teamFrame);

        $playerIndex = -1;
        foreach ($players as $player) {
            $playerIndex += 1;
            $button = $this->teamManager->getPlayerTeam($player->login);
            $color = "000";
            $prefix = "";
            if ($button !== null) {
                $color = $button->color;
                $prefix = $button->chatPrefix;
            }

            if ($this->maniaControl->getPlayerManager()->getPlayer($player->login, true) == null) {
                $prefix = '$999DC';
            }

            $playerFrame = new Frame();
            $teamFrame->addChild($playerFrame);
            $playerFrame->setPosition(2.5, -$playerIndex * 4.5);

            $quad = new Quad();
            $playerFrame->addChild($quad);
            $quad->setSize($width, 4)
                ->setX(-2.5)
                ->setZ(-1)
                ->setHorizontalAlign("left")
                ->setBackgroundColor($color . "3");

            $labelNb = new Label();
            $playerFrame->addChild($labelNb);

            $labelNb->setText($prefix)
                ->setSize(10, 4)
                ->setX(5)
                ->setHorizontalAlign("center")
                ->setTextSize(2)
                ->setTextFont("GameFontBlack")
                ->setTextEmboss(true);

            $label = new Label();
            $playerFrame->addChild($label);
            $label->setText($player->getEscapedNickname())
                ->setSize(50, 4)
                ->setX(12)
                ->setHorizontalAlign("left")
                ->setTextSize(1.5)
                ->setTextFont("GameFontSemiBold")
                ->setTextEmboss(true);
            $offset = 0;
            foreach ($this->teamManager->getTeams() as $index => $teamInfo) {
                $button = new Label_Button();
                $playerFrame->addChild($button);
                $offset = ($index * 11);
                $button->addActionTriggerFeature(self::ACTION_TEAM . "." . $player->login . "." . $teamInfo->id);
                $color = str_split($teamInfo->color);
                $hsl = ColorLib::rgb2hsl(hexdec($color[0]) * 16, hexdec($color[1]) * 16, hexdec($color[2]) * 16);

                $hsl[2] -= 0.3;
                if ($hsl[2] < 0) {
                    $hsl[2] = 0;
                }

                $rgb = ColorLib::hsl2rgb($hsl[0], $hsl[1], $hsl[2]);
                $fixedColor = $this->fn(dechex($rgb[0])) . $this->fn(dechex($rgb[1])) . $this->fn(dechex($rgb[2]));

                $button->setText($teamInfo->chatPrefix)
                    ->setTextSize(1)
                    ->setAreaFocusColor($fixedColor)
                    ->setAreaColor("000")
                    ->setOpacity(0.9)
                    ->setSize(10, 4)
                    ->setX(60 + $offset);
            }

            $team2 = new Label_Button();
            $playerFrame->addChild($team2);
            $team2->addActionTriggerFeature(self::ACTION_REMOVE . "." . $player->login);
            $team2->setText("Remove")
                ->setTextSize(1)
                ->setAreaFocusColor("f00")
                ->setAreaColor("700")
                ->setSize(10, 4)
                ->setOpacity(0.9)
                ->setX(60 + ($offset + 11));
        }

        $maniaLink->addChild($mainFrame);

        $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
        Logger::logInfo("Displayed");
    }

    /**
     * Display Widget Manialinks
     *
     * @param bool|string $diff
     */
    public function displayManialinks($diff = false)
    {
        $this->showTeamWidget(null);

        $mlBackground = $this->getWidgetBackground();
        $mlData = $this->getWidgetData();
        $login = null;

        if (!is_bool($diff)) {
            $login = $diff;
            $diff = false;
        }

        if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_SHOWPLAYERS) ||
            !$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_SHOWSPECTATORS)) {
            $this->closeWidgets();
            return;
        }

        if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_SHOWPLAYERS) &&
            $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_SHOWSPECTATORS)) {
            if (!$diff) {
                $this->maniaControl->getManialinkManager()->sendManialink($mlBackground);
            }
            $this->maniaControl->getManialinkManager()->sendManialink($mlData);
            return;
        }

        /** @var string[] $playerLogins */
        $playerLogins = [];
        /** @var string[] $specLogins */
        $specLogins = [];

        $diffSpecs = [];
        $diffPlayers = [];

        if ($login) {
            $player = $this->maniaControl->getPlayerManager()->getPlayer($login);
            if ($player->isSpectator) {
                $specLogins[] = $player->login;
            } else {
                $playerLogins[] = $player->login;
            }
        } else {
            foreach ($this->maniaControl->getPlayerManager()->getPlayers(true) as $player) {
                $playerLogins[] = $player->login;
            }
            foreach ($this->maniaControl->getPlayerManager()->getSpectators() as $spec) {
                $specLogins[] = $spec->login;
            }
        }

        // In diff mode, get the list of those who need to hide the ML
        if ($diff) {
            $diffSpecs = array_diff($specLogins, $this->specsWithML);
            $diffPlayers = array_diff($playerLogins, $this->playersWithML);
        }

        if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_SHOWPLAYERS)) {
            // hiding the ML from spectators who still have it
            if (count($diffSpecs) > 0) {
                $this->closeWidgets($diffSpecs);
                $specLogins = [];
            }

            if (count($playerLogins) > 0) {
                $this->maniaControl->getManialinkManager()->sendManialink($mlBackground, $playerLogins);
                $this->maniaControl->getManialinkManager()->sendManialink($mlData, $playerLogins);
            }
        }

        if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RMXTEAMSWIDGET_SHOWSPECTATORS)) {
            // hiding the ML from players who still have it
            if (count($diffPlayers) > 0) {
                $this->closeWidgets($diffPlayers);
                $playerLogins = [];
            }

            if (count($specLogins) > 0) {
                if (!$diff) {
                    // if no diff, display the BG for all
                    $this->maniaControl->getManialinkManager()->sendManialink($mlBackground, $specLogins);
                } elseif (count($diffSpecs) > 0) {
                    // diff, display the BG for those who don't have it
                    $this->maniaControl->getManialinkManager()->sendManialink($mlBackground, $diffSpecs);
                }
                // display data
                $this->maniaControl->getManialinkManager()->sendManialink($mlData, $specLogins);
            }
        }

        // Store in memory players/specs with ML (useful for diff mode)
        if (!isset($login)) {
            $this->playersWithML = $playerLogins;
            $this->specsWithML = $specLogins;
        }

    }

    /**
     * Generate the manialink of the background of the widget
     */
    public function getWidgetBackground(): string
    {
        $frame = new Frame();
        $frame->setPosition(0, 90);
        $frame->setZ(-2);

        $sizeX = 30 * count($this->teamManager->teams);

        $backgroundQuad = new Quad();
        $backgroundQuad->setZ(-1);
        $backgroundQuad->setSize($sizeX, 35);
        $backgroundQuad->setVerticalAlign($backgroundQuad::CENTER);
        $backgroundQuad->setHorizontalAlign($backgroundQuad::CENTER);
        $backgroundQuad->setBackgroundColor("000000");
        $backgroundQuad->setOpacity(0.5);
        $frame->addChild($backgroundQuad);

        $frame2 = new Frame();
        $frame2->setPosition(0, 67.5);
        $frame2->setZ(-2);

        $backgroundQuad2 = new Quad();
        $backgroundQuad2->setZ(-1);
        $backgroundQuad2->setSize(55, 10);
        $backgroundQuad2->setVerticalAlign($backgroundQuad2::CENTER);
        $backgroundQuad2->setHorizontalAlign($backgroundQuad2::CENTER);
        $backgroundQuad2->setBackgroundColor("000000");
        if ($this->MatchManagerCore->getMatchStatus()) {
            $backgroundQuad2->setOpacity(0.5);
        } else {
            $backgroundQuad2->setOpacity(0);
        }
        $frame2->addChild($backgroundQuad2);

        $manialink = new ManiaLink(self::MLID_RMXTEAMSWIDGET_LIVE_WIDGETBACKGROUND);
        $manialink->addChild($frame);
        // $manialink->addChild($frame2);
        return $manialink;
    }

    /**
     * Generate the manialink of the data of the widget
     */
    public function getWidgetData(): string
    {
        $teams = $this->teamManager->getTeams();

        $globalframe = new Frame();
        $globalframe->setPosition(-(count($teams) * 30 / 2), 81);
        $globalframe->setZ(1);

        $rank = 1;
        foreach ($teams as $team) {
            $posX = 15 + 30 * ($rank - 1);

            $teamframe = new Frame();
            $globalframe->addChild($teamframe);
            $teamframe->setPosition($posX, 0);

            // Team Name
            $teamnamelabel = new Label();
            $teamnamelabel->setPosition(0, 2.5);
            $teamnamelabel->setSize(25, 20);
            $teamnamelabel->setZ(2);
            $teamnamelabel->setTextFont("GameFontBlack");
            $teamnamelabel->setHorizontalAlign($teamnamelabel::CENTER);
            $teamnamelabel->setTextSize(4);
            $teamnamelabel->setText($team->teamName);
            $teamframe->addChild($teamnamelabel);

            // Players Alive count
            $playercountlabel = new Label();
            $playercountlabel->setPosition(0, -2.5);
            $playercountlabel->setSize(25, 20);
            $playercountlabel->setZ(2);
            $playercountlabel->setTextFont("GameFontBlack");
            $playercountlabel->setHorizontalAlign($playercountlabel::CENTER);
            $playercountlabel->setTextSize(1);
            $teamframe->addChild($playercountlabel);

            $match_started = $this->MatchManagerCore->getMatchStatus();
            if (!$match_started) {
                $playercountlabel->setText(count($team->getPlayers()) . "/" . $this->teamManager->getTeamSize());
            } else {
                $playercountlabel->setText($team->getAliveAmount() . "/" . count($team->getPlayers()));
            }

            // Points
            $pointslabel = new Label();
            $teamframe->addChild($pointslabel);
            $pointslabel->setPosition(0, -5);
            $pointslabel->setSize(25, 20);
            $pointslabel->setZ(2);
            $pointslabel->setTextFont("GameFontBlack");
            $pointslabel->setHorizontalAlign($pointslabel::CENTER);
            $pointslabel->setTextSize(3);
            //$pointslabel->setText('$z' . $team->points); // '$z' to display the 0

            $rank++;
        }

        $winnable_points_frame = new Frame();
        $winnable_points_frame->setPosition(0, 67.5);
        $winnable_points_frame->setZ(1);

        $points_label = new Label();
        $winnable_points_frame->addChild($points_label);
        $points_label->setPosition(0, 0);
        $points_label->setSize(50, 20);
        $points_label->setZ(2);
        $points_label->setTextFont("GameFontBlack");
        $points_label->setHorizontalAlign($points_label::CENTER);
        $points_label->setTextSize(3);

        $manialinkData = new ManiaLink(self::MLID_RMXTEAMSWIDGET_LIVE_WIDGETDATA);
        $manialinkData->addChild($globalframe);
        // $manialinkData->addChild($winnable_points_frame);
        return $manialinkData;
    }

//</editor-fold>
}
