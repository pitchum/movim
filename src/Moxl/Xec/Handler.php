<?php
/*
 * @file Handler.php
 * 
 * @brief Handle incoming XMPP request and dispatch them to the correct 
 * XECElement
 * 
 * Copyright 2012 edhelas <edhelas@edhelas-laptop>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 * 
 */

namespace Moxl\Xec;

use Moxl\Utils;

class Handler {
    static public function handleStanza($xml)
    {
        libxml_use_internal_errors(true);
        $xml = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xml);

        $xml = simplexml_load_string($xml);

        if($xml !== false) {
            self::handle($xml);
        } 
    }

    /**
     * Constructor of class Handler.
     *
     * @return void
     */
    static public function handle($child)
    {
        $_instances = 'empty';

        $user = new \User();
        
        $db = \Modl\Modl::getInstance();
        $db->setUser($user->getLogin());
    
        $id = '';
        $element = '';
        
        // Id verification in the returned stanza
        if($child->getName() == 'iq') {
            $id = (string)$child->attributes()->id;
            $element = 'iq';
        }

        if($child->getName() == 'presence') {
            $id = (string)$child->attributes()->id;
            $element = 'presence';
        }

        if($child->getName() == 'message') {
            $id = (string)$child->attributes()->id;
            $element = 'message';
        }

        $sess = \Session::start();

        if(
            ($id != '' &&
            $sess->get($id) == false) ||
            $id == ''
          ) {
            Utils::log("Handler : Memory instance not found for {$id}");
            Utils::log('Handler : Not an XMPP ACK');

            Handler::handleNode($child);
            
            foreach($child->children() as $s1) {
                Handler::handleNode($s1, $child);  
                foreach($s1->children() as $s2) 
                    Handler::handleNode($s2, $child);  
            }
        } elseif(
            $id != '' &&
            $sess->get($id) != false
        ) {
            // We search an existent instance
            Utils::log("Handler : Memory instance found for {$id}");
            $instance = $sess->get($id);
            
            $action = unserialize($instance->object);

            $error = false;
            
            // Handle specific query error
            if($child->query->error)
                $error = $child->query->error;
            elseif($child->error)
                $error = $child->error;

            // XMPP returned an error
            if($error) {
                $errors = $error->children();

                $errorid = Handler::formatError($errors->getName());

                $message = false;

                if($error->text)
                    $message = (string)$error->text;

                Utils::log('Handler : '.$id.' - '.$errorid);

                /* If the action has defined a special handler
                 * for this error
                 */
                if(method_exists($action, $errorid)) {
                    $action->method($errorid);
                    $action->$errorid($errorid, $message);
                }
                // We also call a global error handler
                if(method_exists($action, 'error')) {
                    Utils::log('Handler : Global error - '.$id.' - '.$errorid);
                    $action->method('error');
                    $action->error($errorid, $message);
                }
            } else {
                // We launch the object handle
                $action->method('handle');
                $action->handle($child);
            }
            // We clean the object from the cache
            $sess->remove($id);
        }
    }
    
    static public function handleNode($s, $sparent = false) {
        $name = $s->getName();
        $ns = $s->getNamespaces();

        $node = false;
        
        if($s->items && $s->items->attributes()->node)
            $node = (string)$s->items->attributes()->node;
        
        if(is_array($ns))
            $ns = current($ns);

        if($node != false) {
            $hash = md5($name.$ns.$node);
            Utils::log('Handler : Searching a payload for "'.$name . ':' . $ns . ' [' . $node . ']", "'.$hash.'"'); 
            Handler::searchPayload($hash, $s, $sparent);
        } else {      
            $hash = md5($name.$ns);
            Utils::log('Handler : Searching a payload for "'.$name . ':' . $ns . ' ", "'.$hash.'"'); 
            Handler::searchPayload($hash, $s, $sparent);
        }
    }

    static function getHashToClass() {
        return array(
            '9b98cd868d07fb7f6d6cb39dad31f10e' => 'Message',#
            '78e731027d8fd50ed642340b7c9a63b3' => 'Message',
            '004a75eb0a92fca2b868732b56863e66' => 'Receipt',
            
            'e83b2aea042b74b1bec00b7d1bba2405' => 'Presence',#
            '362b908ec9432a506f86bed0bae7bbb6' => 'Presence',
            'a0e8e987b067b6b0470606f4f90d5362' => 'Roster',
            
            '89d8bb4741fd3a62e8b20e0d52a85a36' => 'MucUser',
            //'3401c2971b034a441b358af74d777d9d' => 'Subject',
            'b5e3374e43f6544852f7751dfc529100' => 'Subject',
            
            '039538ac1c9488f4a612b89c48a35e32' => 'Post',
            
            '4c9681f0e9aca8a5b65f86b8b80d490f' => 'DiscoInfo',
            '482069658b024085fbc4e311fb771fa6' => 'DiscoInfo',
            
            //'37ff18f136d5826c4426af5a23729e48' => 'Mood',
            '37ff18f136d5826c4426af5a23729e48' => 'Mood',
            '6b38ed328fb77617c6e4a5ac9dda0ad2' => 'Tune',
            '0981a46bbfa88b3500c4bccda18ccb89' => 'Location',
            '9c8ed44d4528a66484b0fbd44b0a9070' => 'Nickname',
            
            //'d8ea912a151202700bb399c9e04d205f' => 'Caps',
            
            '40ed26a65a25ab8bf809dd998d541d95' => 'PingPong',
            
            'cb52f989717d25441018703ea1bc9819' => 'Attention',

            '54c22c37d17c78ee657ea3d40547a970' => 'Version',
            
            '1cb493832467273efa384bbffa6dc35a' => 'Avatar',
            '0f59aa7fb0492a008df1b807e91dda3b' => 'AvatarMetadata',
            '36fe2745bdc72b1682be2c008d547e3d' => 'Vcard4',
            
            'd84d4b89d43e88a244197ccf499de8d8' => 'Jingle',

            '09ef1b34cf40fdd954f10d6e5075ee5c' => 'Carbons',
            '201fa54dd93e3403611830213f5f9fbc' => 'Carbons',

            //'1ad670e043c710f0ce8e6472114fb4be' => 'Register',

            'b95746de5ddc3fa5fbf28906c017d9d8' => 'STARTTLS',
            //'637dd61b00a5ae25ea8d50639f100e7a' => 'STARTTLS',
            '2d6b4b9deec3c87c88839d3e76491a38' => 'STARTTLSProceed',
            
            'f728271d924a04b0355379b28c3183a1' => 'SASL',#
            'd0db71d70348ef1c49f05f59097917b8' => 'SASL',
            'abae1d63bb4295636badcce1bee02290' => 'SASLChallenge',#
            'b04ec0ade3d49b4a079f0e207d5e2821' => 'SASLChallenge',
            '53936dd4e1d64e1eeec6dfc95c431964' => 'SASLSuccess',#
            '260ca9dd8a4577fc00b7bd5810298076' => 'SASLSuccess',
            'de175adc9063997df5b79817576ff659' => 'SASLFailure',
            '0bc0f510b2b6ac432e8605267ebdc812' => 'SessionBind',#
            '128477f50347d98ee1213d71f27e8886' => 'SessionBind',
        );

    }
    
    static public function searchPayload($hash, $s, $sparent = false) {       
        $base = __DIR__.'/';
        
        $hashToClass = self::getHashToClass();
        if(isset($hashToClass[$hash])) {
            if(file_exists($base.'Payload/'.$hashToClass[$hash].'.php')) {
                require_once($base.'Payload/'.$hashToClass[$hash].'.php');
                $classname = '\\Moxl\\Xec\\Payload\\'.$hashToClass[$hash];

                if(class_exists($classname)) {
                    $payload_class = new $classname();
                    $payload_class->prepare($s, $sparent);
                    $payload_class->handle($s, $sparent);
                } else {
                   Utils::log('Handler : Payload class "'.$hashToClass[$hash].'" not found'); 
                }
            } else {
                Utils::log('Handler : Payload file "'.$hashToClass[$hash].'" not found');
            }
        } else {
            Utils::log('Handler : This event is not listed');
            return true;
        }
    }
    
    static public function handleError($number, $message) {
        $payload_class = new Payload\RequestError();
        $payload_class->handle($number, $message);
    }

    /* A simple function to format a error-string-text to a
     * camelTypeText 
     */
    static public function formatError($string) {

        $words = explode('-', $string);
        $f = 'error';
        foreach($words as $word)
            $f .= ucfirst($word);

        return $f;
    }

}
