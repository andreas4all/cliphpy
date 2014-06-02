<?php
namespace Cliphpy\Lib\CAO;
use Cliphpy\Lib\Element;

class Redis extends Element
{

  /**
   * @var Redis
   */
  private $redis;

  /**
   * @var string
   */
  private $key;

  /**
   * @var integer
   */
  private $countGet = 1;

  /**
   * @var integer
   */
  private $countSet = 1;

  public function close($signal){
    $this->disconnect();
    $this->log->info("Redis disconnected.");
  }

  public function connect(){
    $this->redis = new \Redis;
    $this->redis->connect($this->config->{$this->alias}->address,
      $this->config->{$this->alias}->port);
    $this->redis->select($this->config->{$this->alias}->idDatabase);
  }

  public function disconnect(){
    $this->redis->close();
  }

  /**
   * @param  string|array $key
   * @return null|string|boolean|array|object
   */
  public function get(){
    $this->countGet++;
    $key = func_get_args();
    $this->caller();
    $this->generateKey($key);
    $value = unserialize($this->redis->get($this->key));
    if (false === $value){
      return null;
    }
    return $value;
  }

  /**
   * @param string|boolean|array|object $value
   * @param string|array|null $key
   * @return null|string|boolean|array|object
   */
  public function set($value, $key = null){
    $this->countSet++;
    $this->caller();
    $this->generateKey($key);
    return $this->redis->set($this->key, serialize($value));
  }

  /**
   * @return boolean
   * @todo  move logging into upper level
   * @todo  move checkconnection into upper level
   */
  public function flushAll(){
    $this->countGet = 0;
    $this->countSet = 0;
    return $this->redis->flushAll();
  }

  /**
   * @return float
   */
  public function getUsage(){
    if ($this->countGet === 0 || $this->countSet === 0){
      return 0;
    }
    $usage = ($this->countGet) / ($this->countSet);
    $usage *= 100;
    $usage -= 100;
    return $usage;
  }

  /**
   * @return boolean
   * @throws RedisException If connection lost
   */
  public function isConnected(){
    try {
      if (is_array($this->redis->info())){
        return true;
      }
    } catch (\RedisException $e){}
    return false;
  }

  public function checkConnection(){
    try {
      $info = $this->redis->info();
      $msg = "Redis %s, uptime %d min %d sec, memory %s, memory peak %s, " .
        "memory fragmentation %.02f";
      $this->log->info(sprintf($msg, $info["redis_version"],
        ($info["uptime_in_seconds"] / 60), ($info["uptime_in_seconds"] % 60),
        $info["used_memory_human"], $info["used_memory_peak_human"],
        $info["mem_fragmentation_ratio"]));
      $this->log->write();
    } catch (\RedisException $e){
      $this->log->error(json_encode($e));
    }
  }

  /**
   * @param integer|string|array|null $key
   */
  private function generateKey($key){
    if (is_null($key)){
      return $this->key;
    }
    if (is_array($key) && 1 === count($key)){
      $key = $key[0];
    }
    if (is_string($key) || is_int($key)){
      $key = array($key);
    }
    $callerClass = str_replace("\\", ":", $this->callerClass);
    $caller = array($callerClass, $this->callerFunction);
    $this->key = implode(":", array_merge($caller, $key));
  }
}