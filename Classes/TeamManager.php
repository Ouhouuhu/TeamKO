<?php

namespace ouhouuhu\Classes;

use ManiaControl\Players\Player;

class TeamManager
{
    /** @var Team[] */
    public $teams = [];
    protected $teamSize = 10;

    /**
     * @param string $name
     * @param string $prefix
     * @return Team
     */
    public function addTeam(string $name, string $prefix): Team
    {
        $team = new Team();
        $team->teamName = $name;
        $team->chatPrefix = $prefix;
        $i = strpos($prefix, '$');
        $team->color = substr($prefix, $i, 4);
        $team->teamSize = $this->teamSize;
        $this->teams[] = $team;
        return $team;
    }

    /**
     * @param $index integer
     * @return void
     */
    public function removeTeamByIndex(int $index): void
    {
        unset($this->teams[$index]);
    }

    /**
     * @return void
     */
    public function removeTeams(): void
    {
        unset($this->teams);
        $this->teams = [];
    }

    /**
     * @param $name string
     * @return void
     */
    public function removeTeam(string $name): void
    {
        foreach ($this->teams as $idx => $team) {
            if ($team->teamName == $name) {
                unset($this->teams[$idx]);
                break;
            }
        }
    }

    /**
     * @param int $index
     * @return Team|null
     */
    public function getTeam(int $index): ?Team
    {
        if (array_key_exists($index, $this->teams)) return $this->teams[$index];
        return null;
    }

    public function getPlayerTeam(string $login): ?Team
    {
        foreach ($this->teams as $i => $team) {
            if ($team->getPlayer($login) != null) return $team;
        }
        return null;
    }

    /**
     * @param string $login
     * @return int
     */
    public function getPlayerTeamIndex(string $login): int
    {
        foreach ($this->teams as $i => $team) {
            if ($team->getPlayer($login) != null) return $i;
        }
        return -1;
    }

    public function resetPlayerStatuses()
    {
        foreach ($this->teams as $team) {
            $team->points = 0;
            $team->resetKnockout();
            foreach ($team->getPlayers() as $player) {
                $player->isConnected = true;
                $player->isAlive = true;
            }
        }
    }

    /**
     * @param Player $player
     * @param int $teamNb
     * @return KoPlayer
     */
    public function addPlayerToTeam(Player $player, int $teamNb): ?KoPlayer
    {
        $playerTeam = $this->getPlayerTeam($player->login);
        if ($playerTeam !== null) {
            $playerTeam->removePlayer($player);
        }
        $team = $this->getTeam($teamNb);
        if ($team) return $team->addPlayer($player);
        return null;
    }

    /**
     * @return Team[]
     */
    public function getTeams(): array
    {
        return $this->teams;
    }

    /**
     * @param int $teamSize
     */
    public function setTeamSize(int $teamSize): void
    {
        $this->teamSize = $teamSize;
    }

    /**
     * @return int
     */
    public function getTeamSize(): int
    {
        return $this->teamSize;
    }

}