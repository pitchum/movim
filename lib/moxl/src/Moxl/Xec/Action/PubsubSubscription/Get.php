<?php

namespace Moxl\Xec\Action\PubsubSubscription;

use Moxl\Xec\Action;
use Moxl\Xec\Action\Pubsub\Errors;
use Moxl\Stanza\Pubsub;

class Get extends Errors
{
    protected $_to;
    protected $_pepnode = 'urn:xmpp:pubsub:subscription';

    public function request()
    {
        $this->store();
        Pubsub::getItems($this->_to, $this->_pepnode, 1000);
    }

    public function handle($stanza, $parent = false)
    {
        \App\User::me()->subscriptions()
                       ->where('public', ($this->_pepnode == 'urn:xmpp:pubsub:subscription'))
                       ->delete();

        foreach ($stanza->pubsub->items->children() as $i) {
            $subscription = \App\Subscription::firstOrNew([
                'jid' => $this->_to,
                'server' => (string)$i->subscription->attributes()->server,
                'node' => (string)$i->subscription->attributes()->node
            ]);

            if ($this->_pepnode == 'urn:xmpp:pubsub:subscription') {
                $subscription->public = true;
            }
            $subscription->save();
        }

        $this->pack($this->_to);
        $this->deliver();
    }

    public function errorFeatureNotImplemented($error)
    {
        $this->deliver();
    }

    public function errorItemNotFound($error)
    {
        $this->deliver();
    }
}
