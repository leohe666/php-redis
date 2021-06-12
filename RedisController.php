<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RedisController extends Controller
{
    private $redis = null;

    public function index()
    {
        echo 123;
    }

    public function pipeline()
    {
        $time = time();
        $endTime = $time+10;
        $this->getRedis();
        while ($time<$endTime) {
            $time = time();
            try {
                $this->update_token('123','132', 'apple');
                // $this->update_token_pipeline('123','132', 'apple');
            } catch (\Throwable $th) {
                throw new Exception("Error Processing Request", 2);                
            }
        }
        echo 'end';
    }

    private function getRedis()
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    private function update_token($token, $user, $item=null)
    {
        $timestamp = time();
        $this->redis->hSet('login:', $token, $user);
        $this->redis->zadd('recent:', $token, $timestamp);
        if ($item) {
            $this->redis->zadd('viewed:'.$token, $item, $timestamp);
            $this->redis->zremrangebyrank('viewed:'.$token, 0, -26);
            $this->redis->zincrby('viewed:', 1, $item);
        }
    }

    private function update_token_pipeline($token, $user, $item=null)
    {
        $timestamp = time();
        try {
            $this->redis->pipeline();
            $timestamp = time();
            $this->redis->hSet('login:', $token, $user);
            $this->redis->zadd('recent:', $token, $timestamp);
            if ($item) {
                $this->redis->zadd('viewed:'.$token, $item, $timestamp);
                $this->redis->zremrangebyrank('viewed:'.$token, 0, -26);
                $this->redis->zincrby('viewed:', 1, $item);
            }
            $this->redis->exec();
        } catch (\Throwable $th) {
            throw new Exception("Error Processing Request", 1);
            
        }
    }
    //redislock
    public function redis()
    {
        $Redis = ClientFactory::create([
            'server' => 'tcp://redis:6379'
        ]);
        $Lock = new RedisLock(
            $Redis, // Instance of RedisClient,
            'redis-lock', // Key in storage,
        );
        if (!$Lock->acquire(2, 3)) {
            throw new \Exception('Can\'t get a Lock');
        }
        usleep(100);
        $Lock->update(3);
        if ($Redis->get("redis-counter") > 0) {
            $Redis->decr("redis-counter");
            $Lock->release();
        }
    }
}
