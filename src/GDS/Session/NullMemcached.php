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

class NullMemcached
{

    public function get($str_key)
    {
        return false;
    }

    public function set($str_key, $mix_value, $int_timeout)
    {
        return true;
    }

    public function replace($str_key, $mix_value, $int_timeout)
    {
        return true;
    }

    public function delete($str_key)
    {
        return false;
    }

}