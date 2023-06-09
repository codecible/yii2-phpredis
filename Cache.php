<?php

namespace codecible\phpredis;

use Yii;
use yii\di\Instance;

class Cache extends \yii\caching\Cache
{
    /**
     * @var Connection|string|array the Redis [[Connection]] object or the application component ID of the Redis [[Connection]].
     * This can also be an array that is used to create a redis [[Connection]] instance in case you do not want do configure
     * redis connection as an application component.
     * After the Cache object is created, if you want to change this property, you should only assign it
     * with a Redis [[Connection]] object.
     */
    public $redis = 'redis';

    /**
     * Initializes the redis Cache component.
     * This method will initialize the [[redis]] property to make sure it refers to a valid redis connection.
     * @throws \yii\base\InvalidConfigException if [[redis]] is invalid.
     */
    public function init()
    {
        \Yii::trace("cache init ", __CLASS__);
        
        parent::init();
        $this->redis = Instance::ensure($this->redis, Connection::className());
        $this->redis->open();
        
        \Yii::trace("cache init end ", __CLASS__);
    }

	public function buildKey($key)
    {
        if (is_string($key)) {
            //$key = ctype_alnum($key) && StringHelper::byteLength($key) <= 32 ? $key : md5($key);
        } else {
            $key = md5(json_encode($key));
        }
    
        return $this->keyPrefix . $key;
    }


    /**
     * @inheritdoc
     */
    public function exists($key)
    {
        $key = $this->buildKey($key);

        return (bool)$this->redis->exists($key);
    }

    /**
     * @inheritdoc
     */
    protected function getValue($key)
    {
        return $this->redis->get($key);
    }

    /**
     * @inheritdoc
     */
    protected function getValues($keys)
    {
        $response = $this->redis->mget($keys);
        $result = [];
        $i = 0;
        foreach ($keys as $key) {
            $result[$key] = $response[$i++];
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $expire)
    {
        if ($expire == 0) {
            return (bool)$this->redis->set($key, $value);
        } else {
            return (bool)$this->redis->setEx($key, $expire, $value);
        }
    }

    /**
     * @inheritdoc
     */
    protected function setValues($data, $expire)
    {
        $failedKeys = [];
        if ($expire == 0) {
            $this->redis->mSet($data);
        } else {
            $expire = (int)$expire;
            $this->redis->multi();
            $this->redis->mSet($data);
            $index = [];
            foreach ($data as $key => $value) {
                $this->redis->expire($key, $expire);
                $index[] = $key;
            }
            $result = $this->redis->exec();
            array_shift($result);
            foreach ($result as $i => $r) {
                if ($r != 1) {
                    $failedKeys[] = $index[$i];
                }
            }
        }

        return $failedKeys;
    }


    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $expire)
    {
        if ($expire == 0) {
            return (bool)$this->redis->setNx($key, $value);
        }

        return (bool)$this->redis->rawCommand('SET', $key, $value, 'EX', $expire, 'NX');
    }

    /**
     * @inheritdoc
     */
    protected function deleteValue($key)
    {
        return (bool)$this->redis->del($key);
    }

    /**
     * @inheritdoc
     */
    protected function flushValues()
    {
        return $this->redis->flushdb();
    }
}
