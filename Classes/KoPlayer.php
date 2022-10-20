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

}