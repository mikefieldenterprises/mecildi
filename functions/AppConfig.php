<?php

class AppConfig {
    /** @var array */
    public $app;
    /** @var array */
    public $database;
    /** @var array */
    public $langdetect;
    /** @var array */
    public $performance;
    /** @var array */
    public $debug;

    public function __construct($data) {
        $this->app              = isset($data['app'])    ? $data['app']    : [];
        $this->database         = isset($data['database'])    ? $data['database']    : [];
        $this->langdetect       = isset($data['langdetect'])  ? $data['langdetect']  : [];
        $this->performance      = isset($data['performance']) ? $data['performance'] : [];
        $this->debug            = isset($data['debug'])       ? $data['debug']       : [];
    }
    
    /**
     * Helper to check if debug is on
     * @return bool
     */
    public function isDebug() {
        $mode = isset($this->debug['debug_mode']) ? $this->debug['debug_mode'] : 'off';
        return $mode === 'on';
    }
}