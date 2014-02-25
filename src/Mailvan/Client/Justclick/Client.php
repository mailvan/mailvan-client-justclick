<?php


namespace Mailvan\Client\Justclick;

use Mailvan\Core\Client as BaseClient;
use Mailvan\Core\MailvanException;
use Mailvan\Core\Model\SubscriptionListInterface;
use Mailvan\Core\Model\UserInterface;


class Client extends BaseClient
{
    protected function signRequest($params)
    {
        return md5(sprintf("%s::%s::%s",
            http_build_query($params),
            $this->getConfig('username'),
            $this->getConfig('api_key')
        ));
    }

    protected function checkResponseSignature($response)
    {
        $hash = md5(sprintf(
            "%s::%s::%s",
            $response['error_code'],
            $response['error_text'],
            $this->getConfig('api_key')
        ));

        return ($hash == $response['hash']);
    }


    /**
     * Merge API key into params array. Some implementations require to do this.
     *
     * @param $params
     * @return mixed
     */
    protected function mergeApiKey($params)
    {
        return array_merge($params, ['hash' => $this->signRequest($params)]);
    }

    /**
     * Check if server returned response containing error message.
     * This method must return true if servers does return error.
     *
     * @param $response
     * @return mixed
     */
    protected function hasError($response)
    {
        return $response['error_code'] != 0 || !$this->checkResponseSignature($response);
    }

    /**
     * Raises Exception from response data
     *
     * @param $response
     * @return MailvanException
     */
    protected function raiseError($response)
    {
        if ($response['error_code'] == 0) {
            return new JustclickException("Response signature is not valid.");
        }

        return new JustclickException($response['error_text'], $response['error_code']);
    }

    /**
     * Subscribes given user to given SubscriptionList. Returns true if operation is successful
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $list
     * @return boolean
     */
    public function subscribe(UserInterface $user, SubscriptionListInterface $list)
    {
        return $this->doExecuteCommand(
            'subscribe',
            ['rid' => [$list->getId()], 'lead_name' => $user->getName(), 'lead_email' => $user->getEmail()],
            function() {
                return true;
            }
        );
    }

    /**
     * Unsubscribes given user from given SubscriptionList.
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $list
     * @return boolean
     */
    public function unsubscribe(UserInterface $user, SubscriptionListInterface $list)
    {
        return $this->doExecuteCommand(
            'unsubscribe',
            ['lead_email' => $user->getEmail(), 'rass_name' => $list->getId()],
            function() {
                return true;
            }
        );
    }

    /**
     * Moves user from one list to another. In some implementation can create several http queries.
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $from
     * @param SubscriptionListInterface $to
     * @return boolean
     */
    public function move(UserInterface $user, SubscriptionListInterface $from, SubscriptionListInterface $to)
    {
        return $this->unsubscribe($user, $from) && $this->subscribe($user, $to);
    }

    /**
     * Returns list of subscription lists created by owner.
     *
     * @return array
     */
    public function getLists()
    {
        return $this->doExecuteCommand('getLists', [], function($response) {
            return array_map(
                function($item) {
                    return $this->createSubscriptionList($item['rass_name']);
                },
                $response['result']
            );
        });
    }
}