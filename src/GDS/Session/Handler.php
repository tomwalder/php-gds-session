<?php
/**
 * Copyright 2016 Tom Walder
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace GDS\Session;

use GDS\Entity;
use GDS\Gateway\ProtoBuf;
use GDS\Schema;
use GDS\Store;

/**
 * Datastore Session Handler
 *
 * @author Tom Walder <tom@docnet.nu>
 * @package GDS\Session
 */
class Handler implements \SessionHandlerInterface
{

    const DURATION_HOUR = 3600;
    const DURATION_DAY = 86400;
    const DURATION_WEEK = 604800;

    const HANDLE = 'GDSSIDv1';

    /**
     * @var Store
     */
    private $obj_store = null;

    /**
     * @var \Memcached
     */
    private $obj_mc = null;

    /**
     * Session data (serialised)
     *
     * @var string
     */
    private $str_data = '';

    /**
     * @var Entity
     */
    private $obj_session_entity = null;

    /**
     * @var bool
     */
    private $bol_new = false;

    /**
     * Session timeout
     *
     * @var int
     */
    private $int_duration = self::DURATION_DAY;

    /**
     * Configure session handle etc.
     *
     * Use a better hash function than the PHP default.
     *
     * @param int $int_duration
     */
    public function __construct($int_duration = self::DURATION_DAY)
    {
        ini_set('session.hash_function', 'sha512');
        ini_set('session.name', self::HANDLE); // Use our own session name
        ini_set('session.serialize_handler', 'php_serialize'); // More robust serialisation since 5.5.4
        $this->setTimeout($int_duration);
        register_shutdown_function('session_write_close');
        $this->obj_mc = new \Memcached();
    }

    /**
     * Connect the session handler, start the session, configure session duration
     *
     * @param int $int_duration
     */
    public static function start($int_duration = self::DURATION_DAY)
    {
        $obj_handler = new self($int_duration);
        session_set_save_handler(
            [$obj_handler, 'open'],
            [$obj_handler, 'close'],
            [$obj_handler, 'read'],
            [$obj_handler, 'write'],
            [$obj_handler, 'destroy'],
            [$obj_handler, 'gc']
        );
        session_start();

        // Reset (extend) the session cookie lifetime to be from now rather than the session creation
        // @todo review use of "session.cookie_path" rather than hard-coded to '/'
        setcookie(session_name(), session_id(), time() + $int_duration, '/');
    }

    /**
     * NOOP - Nothing to do in our implementation
     *
     * @param string $str_path
     * @param string $str_name
     * @return bool
     */
    public function open($str_path, $str_name)
    {
        return true;
    }

    /**
     * NOOP - Nothing to do in our implementation
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * Delete a specific session
     *
     * @param string $str_id
     * @return bool
     */
    public function destroy($str_id)
    {
        if($this->obj_session_entity instanceof Entity && $str_id === $this->obj_session_entity->getKeyName()) {
            $this->getStore()->delete($this->obj_session_entity);
            $this->obj_session_entity = null;
            $this->str_data = '';
            $this->bol_new = false;
        } else {
            $obj_session_entity = $this->getStore()->fetchByName($str_id);
            if($obj_session_entity instanceof Entity) {
                $this->getStore()->delete($obj_session_entity);
            }
        }
        $this->obj_mc->delete($this->getMemcacheKey($str_id));
        return true;
    }

    /**
     * NOOP
     *
     * @param int $int_lifetime
     * @return bool
     */
    public function gc($int_lifetime)
    {
        return true;
    }

    /**
     * Read the session. Ideally from Memcache (fast), but if not from Datastore.
     *
     * @param string $str_id
     * @return mixed|string
     */
    public function read($str_id)
    {
        $str_memcache_key = $this->getMemcacheKey($str_id);
        $this->str_data = $this->obj_mc->get($str_memcache_key);
        if(false === $this->str_data) {
            // not in Memcache, trying datastore
            $this->obj_session_entity = $this->getStore()->fetchByName($str_id);
            if($this->obj_session_entity instanceof Entity) {
                // @todo Validate session age against duration
                $this->str_data = (string)$this->obj_session_entity->data;
                // found in Datastore, updating Memcache
                $this->obj_mc->set($str_memcache_key, $this->str_data, $this->getTimeout());
            } else {
                // New session!
                $this->bol_new = true;
                $this->str_data = '';
            }
        }
        return $this->str_data;
    }

    /**
     * ONLY write if the data has changed
     *
     * We should cache new (probably empty) sessions to stop constantly trying to FETCH from Datastore
     *
     * @param string $str_id
     * @param string $str_session_data
     * @return bool
     */
    public function write($str_id, $str_session_data)
    {
        if($this->str_data == $str_session_data) {
            if($this->bol_new) {
                $this->cache($str_id, $str_session_data);
            }
        } else {
            $this->cache($str_id, $str_session_data);
            $this->persist($str_id, $str_session_data);
        }
        return true;
    }

    /**
     * Write to the cache
     *
     * @param $str_id
     * @param $str_session_data
     */
    private function cache($str_id, $str_session_data)
    {
        // syslog(LOG_WARNING, __METHOD__ . "() Writing to Memcache");
        try {
            $str_memcache_key = $this->getMemcacheKey($str_id);
            $bol_replaced = $this->obj_mc->replace($str_memcache_key, $str_session_data, $this->getTimeout());
            if (false === $bol_replaced) {
                $this->obj_mc->set($str_memcache_key, $str_session_data, $this->getTimeout());
            }
        } catch (\Exception $obj_ex) {
            syslog(LOG_WARNING, __METHOD__ . "() Unable to write to Memcache: " . $obj_ex->getMessage());
        }

    }

    /**
     * Write to Datastore
     *
     * @param $str_id
     * @param $str_session_data
     */
    private function persist($str_id, $str_session_data)
    {
        // syslog(LOG_WARNING, __METHOD__ . "() Writing to Datastore");
        try {
            $obj_store = $this->getStore();
            $str_now = date('Y-m-d H:i:s');
            if ($this->obj_session_entity instanceof Entity) {
                $this->obj_session_entity->data = $str_session_data;
                $this->obj_session_entity->updated = $str_now;
            } else {
                $this->obj_session_entity = $obj_store->createEntity([
                    'data' => $str_session_data,
                    'created' => $str_now,
                    'updated' => $str_now
                ]);
                $this->obj_session_entity->setKeyName($str_id);
            }
            $obj_store->upsert($this->obj_session_entity);
        } catch (\Exception $obj_ex) {
            syslog(LOG_WARNING, __METHOD__ . "() Unable to write to Datastore: " . $obj_ex->getMessage());
        }
    }

    /**
     * Only one Store object per instance
     *
     * @return Store
     */
    private function getStore()
    {
        if(null === $this->obj_store) {
            $this->obj_store = new Store($this->createSchema(), new ProtoBuf());
        }
        return $this->obj_store;
    }

    /**
     * Create a Schema to represent session data in Datastore
     *
     * Updated timestamp indexed to allow for purging old sessions
     *
     * @return Schema
     */
    private function createSchema()
    {
        return (new Schema('GDS_Session'))
            ->addString('data', false)
            ->addDatetime('created', false)
            ->addDatetime('updated', true);
    }

    /**
     * Session timeout
     *
     * @return int
     */
    private function getTimeout()
    {
        return $this->int_duration;
    }

    /**
     * Set the session timeout
     *
     * @param $int_duration
     * @return int
     */
    public function setTimeout($int_duration)
    {
        $this->int_duration = $int_duration;
        ini_set('session.cookie_lifetime', $int_duration);
    }

    /**
     * Generate Memcache key (in one place!)
     *
     * @param $str_id
     * @return string
     */
    private function getMemcacheKey($str_id)
    {
        return 'GDS_Session_' . self::HANDLE . '_' . $str_id;
    }

}
