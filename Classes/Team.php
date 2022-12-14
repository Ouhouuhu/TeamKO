<?php

namespace ouhouuhu\Classes;

use ManiaControl\Players\Player;

class Team
{
    /** @var integer */
    public $id;
    /** @var string */
    public $teamName;
    /** @var string */
    public $chatPrefix;
    /** @var string */
    public $color;

    /** @var KoPlayer[] */
    protected $players = [];
    /** @var string[] */
    protected $knockedOutLogins = [];
    /** @var int */
    public $teamSize;

    /**
     * @param Player $player
     * @return KoPlayer|null
     */
    public function addPlayer(Player $player): ?KoPlayer
    {
        $koPlayer = new KoPlayer($player);
        if (sizeof($this->players) + 1 <= $this->teamSize) {
            $this->players[$player->login] = $koPlayer;
            $koPlayer->isAlive = true;
            $koPlayer->isConnected = true;
            return $koPlayer;
        }
        return null;
    }

    /**
     * @param Player $player
     */
    public function removePlayer(Player $player): void
    {
        if (array_key_exists($player->login, $this->players)) unset($this->players[$player->login]);
    }

    /**
     * @param string $login
     */
    public function removePlayerByLogin(string $login): void
    {
        if (array_key_exists($login, $this->players)) unset($this->players[$login]);
    }

    /**
     * @return string[]
     */
    public function getPlayerLogins(): array
    {
        $out = [];
        foreach ($this->players as $player) {
            $out[] = $player->login;
        }
        return $out;
    }

    /**
     * @param string $login
     * @return KoPlayer
     */
    public function getPlayer(string $login): ?KoPlayer
    {
        if (array_key_exists($login, $this->players))
            return $this->players[$login];
        return null;
    }

    /**
     * @return void
     */
    public function resetKnockout(): void
    {
        $this->knockedOutLogins = [];
    }

    /**
     * @return array|null
     */
    public function getKnockoutOrder(): array
    {
        $logins = array_reverse($this->knockedOutLogins);
        $out = [];
        foreach ($logins as $i => $login) {
            $out[$login] = $i;
        }
        return $out;
    }

    public function setKnockout(string $login)
    {
        $player = $this->getPlayer($login);
        if ($player !== null) $player->isAlive = false;
        array_unshift($this->knockedOutLogins, $login);
    }

    /**
     * @return string|null
     */
    public function getReviveLogin(): ?string
    {
        return array_pop($this->knockedOutLogins);
    }

    /**
     * @return KoPlayer[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    /**
     * @return int
     */
    public function getAliveAmount(): int
    {
        $alive = 0;
        foreach ($this->players as $player) {
            if ($player->isAlive && $player->isConnected) {
                $alive += 1;
            }
        }
        return $alive;
    }


}