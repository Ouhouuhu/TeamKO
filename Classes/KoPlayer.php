<?php

namespace ouhouuhu\Classes;


use ManiaControl\Players\Player;

class KoPlayer
{
    /** @var Player */
    public $player;
    /** @var string */
    public $login;
    /** @var bool */
    public $isAlive = false;
    /** @var bool */
    public $isConnected = false;

    public function __construct(Player $player)
    {
        $this->player = $player;
        $this->login = $player->login;
    }

    /**
     * @return string
     */
    public function getStatus():string {
        if (!$this->isConnected) return '$999DC';
        if ($this->isAlive) return '$0d0Alive';
        return '$d00KO';
    }
}