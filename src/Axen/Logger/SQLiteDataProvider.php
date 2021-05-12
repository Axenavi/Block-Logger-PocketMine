<?php

namespace xdming\WhatTheServer;

use Axen\Logger\log;
use Axen\Logger\eventmanager;

class SQLiteDataProvider {

    private $log , $database;

    public function __construct(log $plugin) {
        $this->log = $plugin;
        if(!file_exists($this->log->getDataFolder() . "ServerLog.db")) {
            $this->database = new \SQLite3($this->log->getDataFolder() . "ServerLog.db");
            $this->database->exec("CREATE TABLE IF NOT EXISTS ServerLog
                                            (id INTEGER PRIMARY KEY AUTOINCREMENT, date INTEGER, time INTEGER, player TEXT,
                                            level TEXT, x INTEGER, y INTEGER, z INTEGER, event INTEGER, objectid INTERGER, amount INETEGER);");
			$this->database->exec("CREATE TABLE IF NOT EXISTS PlayerLog (id INTEGER PRIMARY KEY AUTOINCREMENT,
									player TEXT, identity TEXT, join_date INTEGER, last_join INETEGER, last_online INTEGER);");
            $this->log->database = $this->database;
        } else{
            $this->database = new \SQLite3($this->log->getDataFolder() . "ServerLog.db");
            $this->log->database = $this->database;
        }
    }

}
