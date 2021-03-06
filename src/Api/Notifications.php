<?php

namespace Tomloprod\IonicApi\Api;

/**
 * Class Notifications
 *
 * Stores ionic push api methods related to notifications collection.
 * More info: https://docs.ionic.io/api/endpoints/push.html
 *
 * @package Tomloprod\IonicApi\Api
 * @author Tomás L.R (@tomloprod)
 * @author Ramon Carreras (@ramoncarreras)
 */
class Notifications extends Request {

    public $requestData = [];
    private $ionicProfile;
    private static $endPoints = [
        'list' => '/push/notifications', // GET
        'create' => '/push/notifications', // POST
        'retrieve' => '/push/notifications/:notification_id', // GET
        'replace' => '/push/notifications/:notification_id', // PUT
        'delete' => '/push/notifications/:notification_id', // DELETE
        'listMessages' => '/push/notifications/:notification_id/messages', // GET
    ];

    /**
     * Notifications constructor.
     *
     * @param string $ionicProfile
     * @param string $ionicAPIToken
     */
    public function __construct($ionicProfile, $ionicAPIToken)
    {
        parent::__construct($ionicProfile, $ionicAPIToken);
        $this->ionicProfile = $ionicProfile;
        $this->requestData = ['profile' => $this->ionicProfile];
    }

    /**
     * Set notification config.
     *
     * @param array $notificationData
     * @param array $payloadData - Custom extra data
     * @param bool $silentNotification - Determines if the message should be delivered as a silent notification.
     * @param string $scheduledDateTime - Time to start delivery of the notification Y-m-d H:i:s format
     * @param string $sound - Filename of audio file to play when a notification is received.
     */
    public function setConfig($notificationData, $payloadData = [], $silentNotification = false, $scheduledDateTime = '', $sound = 'default') {
        if (!is_array($notificationData)) {
            $notificationData = [$notificationData];
        }
        if (count($notificationData) > 0) {
            $this->requestData = array_merge($this->requestData, ['notification' => $notificationData]);
        }

        // payload
        if (!is_array($payloadData)) {
            $payloadData = [$payloadData];
        }
        if (count($payloadData) > 0) {
            $this->requestData['notification']['payload'] = $payloadData;
        }

        // silent
        if ($silentNotification) {
            $this->requestData['notification']['android']['content_available'] = 1;
            $this->requestData['notification']['ios']['content_available'] = 1;
        } else {
            unset($this->requestData['notification']['android']['content_available']);
            unset($this->requestData['notification']['ios']['content_available']);
        }

        // scheduled
        if($this->isDatetime($scheduledDateTime)) {
            // Convert dateTime to RFC3339
            $this->requestData['scheduled'] = date("c", strtotime($scheduledDateTime));
        }
	    
	// sound
 	$this->requestData['notification']['android']['sound'] = $sound;
    	$this->requestData['notification']['ios']['sound'] = $sound;
    }

    /**
     * Paginated listing of Push Notifications.
     *
     * @param array $parameters
     * @param boolean $decodeResponse - Indicates whether the JSON response will be converted to a PHP variable before return.
     * @return array|object|null $response - An array when $decodeResponse is false, an object when $decodeResponse is true, and null when $decodeResponse is true and there is no data on response.
     */
    public function paginatedList($parameters = [], $decodeResponse = true) {
        $response =  $this->sendRequest(
            self::METHOD_GET, 
            self::$endPoints['list'] . '?' . http_build_query($parameters), 
            $this->requestData
        );
        $this->resetRequestData();
	return ($decodeResponse) ? self::decodeResponse($response) : $response;
    }

    /**
     * Get a Notification.
     *
     * @param string $notificationId - Notification id
     * @param boolean $decodeResponse - Indicates whether the JSON response will be converted to a PHP variable before return.	 
     * @return array|object|null $response - An array when $decodeResponse is false, an object when $decodeResponse is true, and null when $decodeResponse is true and there is no data on response.
     */
    public function retrieve($notificationId, $decodeResponse = true) {
        $response = $this->sendRequest(
            self::METHOD_GET,
            str_replace(':notification_id', $notificationId, self::$endPoints['retrieve']),
            $this->requestData
        );
        $this->resetRequestData();
        return ($decodeResponse) ? self::decodeResponse($response) : $response;
    }

    // TODO: replace

    /**
     * Deletes a notification.
     *
     * @param $notificationId
     * @return boolean
     */
    public function delete($notificationId) {
        $response = $this->sendRequest(
            self::METHOD_DELETE,
            str_replace(':notification_id', $notificationId, self::$endPoints['delete'])
        );
	return (empty($response)) ? true : false;
    }

    /**
     * Deletes all notifications
     *
     * @return boolean $allDeleted - Indicate if all notifications have been deleted
     */
    public function deleteAll(){
        $allDeleted = true;
        $notifications = self::paginatedList();
        // If response is an object with data, we loop through each notification and delete.
        if(is_object($notifications) && property_exists($notifications, "data")) {
           foreach($notifications->data as $notification) {
                // If delete response is not empty, the notification has not been deleted.
                if(!empty(self::delete($notification->uuid))) {
                    $allDeleted = false;
                }
           } 
        } else {
           $allDeleted = false;
        }
        return $allDeleted;
    }
    
    /**
     * List messages of the indicated notification.
     *
     * @param string $notificationId - Notification id
     * @param array $parameters
     * @param boolean $decodeResponse - Indicates whether the JSON response will be converted to a PHP variable before return.	 
     * @return array|object|null $response - An array when $decodeResponse is false, an object when $decodeResponse is true, and null when $decodeResponse is true and there is no data on response.
     */
    public function listMessages($notificationId, $parameters = [], $decodeResponse = true) {
        $endPoint = str_replace(':notification_id', $notificationId, self::$endPoints['listMessages']);
        $response =  $this->sendRequest(
            self::METHOD_GET, 
            $endPoint . '?' . http_build_query($parameters), 
            $this->requestData
        );
        $this->resetRequestData();
	return ($decodeResponse) ? self::decodeResponse($response) : $response;
    }

    /**
     * Send push notification for the indicated device tokens.
     *
     * @param array $deviceTokens
     * @return array
     */
    public function sendNotification($deviceTokens) {
        $this->requestData['tokens'] = $deviceTokens;
        $this->requestData['send_to_all'] = false;
        return $this->create();
    }

    /**
     * Send push notification for all registered devices.
     *
     * @return array
     */
    public function sendNotificationToAll() {
        $this->requestData['send_to_all'] = true;
        return $this->create();
    }

    /**
     * Create a Push Notification.
     *
     * Used by "sendNotification" and "sendNotificationToAll".
     *
     * @private
     * @return array
     */
    private function create() {
        $response = $this->sendRequest(
            self::METHOD_POST, 
            self::$endPoints['create'], 
            $this->requestData
        );
        $this->resetRequestData();
        return $response;
    }

    /**
     * Reinitialize requestData.
     *
     * @private
     */
    private function resetRequestData() {
        $this->requestData = ['profile' => $this->ionicProfile];
    }

    /**
     * Validates a datetime (format YYYY-MM-DD HH:MM:SS)
     *
     * @param string $dateTime
     * @return bool
     */
    private function isDatetime($dateTime) {
        if (preg_match('/^(\d{4})-(\d\d?)-(\d\d?) (\d\d?):(\d\d?):(\d\d?)$/', $dateTime, $matches)) {
            return checkdate($matches[2], $matches[3], $matches[1]) && $matches[4] / 24 < 1 && $matches[5] / 60 < 1 && $matches[6] / 60 < 1;
        }
        return false;
    }

}
