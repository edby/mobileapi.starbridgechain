<?php

namespace redis;

/**
 * Class Redis
 * @package redis
 */
class Redis
{
    /**
     * redis对象
     * @var null
     */
    private static $redis = null;

    /**
     * Redis constructor.
     */
    private function __construct(){}

    /**
     * clone
     */
    private function __clone(){}

    /**
     * 获取redis对象
     * @return null|\Redis
     */
    public static function instance()
    {
        $redis_config = config('redis');
        if(!self::$redis || !(self::$redis instanceof \Redis)){
            self::$redis = new \Redis();
            self::$redis->connect($redis_config['host'],$redis_config['port']);
            //密码
            $password = $redis_config['password'];
            if('' !== $password){
                self::$redis->auth($password);
            }
        }
        return self::$redis;
    }
}