<?php

namespace Modules\Feeds\Http\Controllers;

use App\ApiConfig\ApiConfig;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Session;
use Modules\User\helper;

class FeedsController extends Controller
{
    protected $helper;
    protected $API_URL;
    protected $API_VERSION;


    public function __construct()
    {
        $this->API_URL = env('API_URL');
        $this->API_URL_FEEDS = env('API_URL_FEEDS');
        $this->API_VERSION = env('API_VERSION');
        $this->helper = Helper::getInstance();
    }

    /**
     * TODO we've to get  feeds from particular social account of particular social account type.
     * This function is used for getting feeds from particular social account of particular social account type //twitter facebook or youtube
     * by hitting API from controller.
     * @param {string) particulat social account type (twitter ,facebook ,youtube).
     * ! Do not change this function without referring API format getting the particular feeds from feeds servies.
     * @return {object} Returns feeds from social accounts in JSON object format.
     */
    public function getFeedsSocialAccounts($network)
    {
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $twitterAccounts = [];
        $accounts = [];
        $updatedAccounts = [];
        $fbPages = [];
        $fbAccs = [];
        $fballAccs = [];
        $youtubeAccounts = [];
        $instagramAccounts = [];
        if (strpos($network, 'twitter') !== false) {
            try {
                $apiUrl = ApiConfig::get('/team/get-team-details?teamId=' . $teamID);
                $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
                if ($response['data']->code === 200) {
                    foreach ($response['data']->data->teamSocialAccountDetails[0]->SocialAccount as $data) {
                        if ($data->account_type === 4 && $data->join_table_teams_social_accounts->is_account_locked === false) {
                            array_push($twitterAccounts, $data);
                        }
                    }
                    if (count($twitterAccounts) > 0) {
                        if (preg_match('~[0-9]+~', $network)) {//it will check if the network variable has numeric digits or not
                            $accId = (integer)substr($network, 7, strlen($network));
                            for ($i = 0; $i < count($twitterAccounts); $i++) {
                                if ($twitterAccounts[$i]->account_id === $accId) {
                                    array_push($accounts, $twitterAccounts[$i]);
                                    unset($twitterAccounts[$i]);
                                    break;
                                }
                            }
                            if (count($twitterAccounts) > 0) {
                                foreach ($twitterAccounts as $data) {
                                    array_push($updatedAccounts, $data);
                                }
                                for ($j = 0; $j < count($updatedAccounts); $j++) {
                                    array_push($accounts, $updatedAccounts[$j]);
                                }
                            }
                            $feedsData = $this->getTwitterFeeds($accId);
                        } else {
                            $accounts = $twitterAccounts;
                            $accId = $accounts[0]->account_id;
                            $feedsData = $this->getTwitterFeeds($accId);
                        }
                        return view('feeds::twitter_feeds')->with(["code" => 200, "accounts" => $accounts, 'message' => 'success', 'feeds' => $feedsData]);
                    } else {
                        return view('feeds::twitter_feeds')->with(["code" => 200, "accounts" => $accounts, 'message' => 'No Twitter account added yet! or Account has locked']);
                    }
                } else if ($response['data']->code === 400) {
                    return view('feeds::twitter_feeds')->with(["code" => 400, "accounts" => $accounts, 'message' => $response['data']->message]);
                } else {
                    return view('feeds::twitter_feeds')->with(["code" => 500, 'message' => 'failed']);
                }
            } catch (Exception $e) {
                $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getFeedsSocialAccounts() {FeedsController}');
                return view('feeds::twitter_feeds')->with(["code" => 500, 'message' => 'failed']);
            }
        } else if (strpos($network, 'facebook') !== false) {
            try {
                $apiUrl = ApiConfig::get('/team/get-team-details?teamId=' . $teamID);
                $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
                if ($response['data']->code === 200) {
                    foreach ($response['data']->data->teamSocialAccountDetails[0]->SocialAccount as $data) {
                        if ($data->account_type === 2 || $data->account_type === 1 && $data->join_table_teams_social_accounts->is_account_locked === false) {
                            array_push($fballAccs, $data);
                        }
                        if ($data->account_type === 2 && $data->join_table_teams_social_accounts->is_account_locked === false) {
                            array_push($fbPages, $data);
                        }
                        if ($data->account_type === 1 && $data->join_table_teams_social_accounts->is_account_locked === false) {
                            array_push($fbAccs, $data);
                        }
                    }
                    if (count($fbPages) > 0 && count($fbAccs) === 0) {
                        if (preg_match('~[0-9]+~', $network)) {
                            $accId = (integer)substr($network, 8, strlen($network));
                            for ($i = 0; $i < count($fbPages); $i++) {
                                if ($fbPages[$i]->account_id === $accId) {
                                    array_push($accounts, $fbPages[$i]);
                                    unset($fbPages[$i]);
                                    break;
                                }
                            }
                            if (count($fbPages) > 0) {
                                foreach ($fbPages as $data) {
                                    array_push($updatedAccounts, $data);
                                }
                                for ($j = 0; $j < count($updatedAccounts); $j++) {
                                    array_push($accounts, $updatedAccounts[$j]);
                                }
                            }
                        } else {
                            $accId = $fbPages[0]->account_id;
                            $accounts = $fbPages;
                        }
                        $feedsData = $this->getFbPagesFeeds($accId, 2);
                        return view('feeds::facebook_Feeds')->with(["accounts" => $accounts, 'message' => 'success', 'feeds' => $feedsData]);
                    } else if (count($fbAccs) > 0 && count($fbPages) === 0) {
                        if (preg_match('~[0-9]+~', $network)) {
                            $accId = (integer)substr($network, 8, strlen($network));
                            for ($i = 0; $i < count($fbAccs); $i++) {
                                if ($fbAccs[$i]->account_id === $accId) {
                                    array_push($accounts, $fbAccs[$i]);
                                    unset($fbAccs[$i]);
                                    break;
                                }
                            }
                            if (count($fbAccs) > 0) {
                                foreach ($fbAccs as $data) {
                                    array_push($updatedAccounts, $data);
                                }
                                for ($j = 0; $j < count($updatedAccounts); $j++) {
                                    array_push($accounts, $updatedAccounts[$j]);
                                }
                            }
                        } else {
                            $accId = $fbAccs[0]->account_id;
                            $accounts = $fbAccs;
                        }
                        $feedsData = $this->getFbPagesFeeds($accId, 1);
                        return view('feeds::facebook_Feeds')->with(["accounts" => $accounts, 'message' => 'success', 'feeds' => $feedsData]);
                    } else if (count($fbAccs) > 0 && count($fbPages) > 0) {
                        if (preg_match('~[0-9]+~', $network)) {
                            $accId = (integer)substr($network, 8, strlen($network));
                            for ($i = 0; $i < count($fballAccs); $i++) {
                                if ($fballAccs[$i]->account_id === $accId) {
                                    array_push($accounts, $fballAccs[$i]);
                                    $accType = $fballAccs[$i]->account_type;
                                    unset($fballAccs[$i]);
                                    break;
                                }
                            }
                            if (count($fballAccs) > 0) {
                                foreach ($fballAccs as $data) {
                                    array_push($updatedAccounts, $data);
                                }
                                for ($j = 0; $j < count($updatedAccounts); $j++) {
                                    array_push($accounts, $updatedAccounts[$j]);
                                }
                            }
                        } else {
                            $accId = $fballAccs[0]->account_id;
                            $accType = $fballAccs[0]->account_type;
                            $accounts = $fballAccs;
                        }
                        $feedsData = $this->getFbPagesFeeds($accId, $accType);
                        return view('feeds::facebook_Feeds')->with(["accounts" => $accounts, 'message' => 'success', 'feeds' => $feedsData]);
                    } else {
                        return view('feeds::facebook_Feeds')->with(["accounts" => $fballAccs, 'message' => 'No facebook pages or Accounts added yet!']);
                    }

                } else if ($response['data']->code === 400) {
                    return view('feeds::facebook_Feeds')->with(["accounts" => $fbPages, 'message' => 'failed']);
                }

            } catch (Exception $e) {
                $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getFeedsSocialAccounts() {FeedsController}');
                return view('feeds::facebook_Feeds')->with(['message' => 'failed']);
            }
        } else if (strpos($network, 'youtube') !== false) {
            try {
                $apiUrl = ApiConfig::get('/team/get-team-details?teamId=' . $teamID);
                $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
                if ($response['data']->code === 200) {
                    foreach ($response['data']->data->teamSocialAccountDetails[0]->SocialAccount as $data) {
                        if ($data->account_type === 9 && $data->join_table_teams_social_accounts->is_account_locked === false) {
                            array_push($youtubeAccounts, $data);
                        }
                    }
                    if (count($youtubeAccounts) > 0) {
                        if (preg_match('~[0-9]+~', $network)) {//it will check if the network variable has numeric digits or not
                            $accId = (integer)substr($network, 7, strlen($network));
                            for ($i = 0; $i < count($youtubeAccounts); $i++) {
                                if ($youtubeAccounts[$i]->account_id === $accId) {
                                    array_push($accounts, $youtubeAccounts[$i]);
                                    unset($youtubeAccounts[$i]);
                                    break;
                                }
                            }
                            if (count($youtubeAccounts) > 0) {
                                foreach ($youtubeAccounts as $data) {
                                    array_push($updatedAccounts, $data);
                                }
                                for ($j = 0; $j < count($updatedAccounts); $j++) {
                                    array_push($accounts, $updatedAccounts[$j]);
                                }
                            }
                        } else {
                            $accounts = $youtubeAccounts;
                            $accId = $youtubeAccounts[0]->account_id;
                        }
                        $feedsData = $this->getYoutubeFeeds($accId);
                        return view('feeds::youtube_feeds')->with(["accounts" => $accounts, 'message' => 'success', 'feeds' => $feedsData, 'followersData' => $response['data']]);
                    } else {
                        return view('feeds::youtube_feeds')->with(["accounts" => $accounts, 'message' => 'No Youtube account has been added yet!']);

                    }
                } else if ($response['data']->code === 400) {
                    return view('feeds::youtube_feeds')->with(["accounts" => $youtubeAccounts, 'message' => 'failed']);
                }

            } catch (Exception $e) {
                $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getFeedsSocialAccounts() {FeedsController}');
                return view('feeds::youtube_feeds')->with(['message' => 'failed']);
            }
        }
        else if (strpos($network, 'instagram') !== false) {
            try {
                $apiUrl = ApiConfig::get('/team/get-team-details?teamId=' . $teamID);
                $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
                if ($response['data']->code === 200) {
                    foreach ($response['data']->data->teamSocialAccountDetails[0]->SocialAccount as $data) {
                        if ($data->account_type === 5 && $data->join_table_teams_social_accounts->is_account_locked === false) {
                            array_push($instagramAccounts, $data);
                        }
                    }
                    if (count($instagramAccounts) > 0) {
                        if (preg_match('~[0-9]+~', $network)) {//it will check if the network variable has numeric digits or not
                            $accId = (integer)substr($network, 9, strlen($network));
                            for ($i = 0; $i < count($instagramAccounts); $i++) {
                                if ($instagramAccounts[$i]->account_id === $accId) {
                                    array_push($accounts, $instagramAccounts[$i]);
                                    unset($instagramAccounts[$i]);
                                    break;
                                }
                            }
                            if (count($instagramAccounts) > 0) {
                                foreach ($instagramAccounts as $data) {
                                    array_push($updatedAccounts, $data);
                                }
                                for ($j = 0; $j < count($updatedAccounts); $j++) {
                                    array_push($accounts, $updatedAccounts[$j]);
                                }
                            }
                        } else {
                            $accounts = $instagramAccounts;
                            $accId = $instagramAccounts[0]->account_id;
                        }
                        $feedsData = $this->getInstagramFeeds($accId);
//                        dd($feedsData);
                        return view('feeds::instagram_Feeds')->with(["accounts" => $accounts, 'message' => 'success', 'feeds' => $feedsData, 'followersData' => $response['data']]);
                    } else {
                        return view('feeds::instagram_Feeds')->with(["accounts" => $accounts, 'message' => 'No Instagram account has been added yet!']);

                    }
                } else if ($response['data']->code === 400) {
                    return view('feeds::instagram_Feeds')->with(["accounts" => $instagramAccounts, 'message' => 'failed']);
                }

            } catch (Exception $e) {
                $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getFeedsSocialAccounts() {FeedsController}');
                return view('feeds::youtube_feeds')->with(['message' => 'failed']);
            }
        }
    }

    /**
     * TODO we have to perform the retweet operation from sociobaord.
     * This function is used to perform the retweeting a post operation from particular twitter account from sociobaord.
     * @param {integer} twitterID- twitterID of that particular twitter account.
     * @param {integer} accounId-  account id of that particular twitter account.
     * @return {object} Returns if tweet as retweeted or not with message in json object format.
     * ! Do not change this function without referring API format of retweeeting particular tweet.
     */
    public function retweetThisTweet(Request $request)
    {
        $tweetId = (int)$request->twitterID;
        $accounId = (int)$request->accounId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        try {
            $apiUrl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/twitter-retweet?accountId=' . $accounId . '&teamId=' . $teamID . '&tweetId=' . $tweetId;
            $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
            return $this->helper->responseHandler($response['data']);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'disLikeTheTweet() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant retweet this tweet';
            return $result;
        }
    }

    /**
     * TODO we have to perform oepration from getting twitter feeds of particular twitter account by hitting twitter API.
     * This function is used to perform the operation of getting feeds from particular twitter account.
     * @param {Integer} accounId-  account id of that particular twitter account.
     * @return {object} Returns feeds of particular twitter acciount from twitter site  in json object format.
     * ! Do not change this function without referring API format of getting twitter feeds.
     */
    public function getTwitterFeeds($accID)
    {
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $pageId = 1;
        $result = [];
        try {
            $apiUrl1 = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/fetch-all-tweets?accountId=' . $accID . '&teamId=' . $teamID;
            $response2 = $this->helper->postApiCallWithAuth('put', $apiUrl1);
            if ($response2['data']->code === 200) {
                $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-tweets?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
                $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
                if ($response['data']->code === 200) {
                    $result['code'] = 200;
                    $result['data'] = $response['data']->data;
                    return $result;
                } else if ($response['data']->code === 400) {
                    $result['code'] = 400;
                    $result['message'] = $response['data']->error;
                    return $result;
                } else {
                    $result['code'] = 500;
                    $result['message'] = 'Some error occured cant get feeds';
                    return $result;
                }
            } else {
                $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-tweets?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
                $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
                if ($response['data']->code === 200) {
                    $result['code'] = 200;
                    $result['data'] = $response['data']->data;
                    return $result;
                } else if ($response['data']->code === 400) {
                    $result['code'] = 400;
                    $result['message'] = $response['data']->error;
                    return $result;
                } else {
                    $result['code'] = 500;
                    $result['message'] = 'Some error occured cant get feeds';
                    return $result;
                }
            }

        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getTwitterFeeds() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we have to perform oepration from getting youtube feeds of particular youtube channel account by hitting youtube API.
     * This function is used to perform the operation of getting feeds from particular youtube channel account.
     * @param {Integer} accID-  account id of that particular youtube account.
     * @return {object} Returns feeds of particular youtube acciount from youtubr channel site  in json object format.
     * ! Do not change this function without referring API format of getting youtube channel feeds.
     */
    public function getYoutubeFeeds($accID)
    {
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $pageId = 1;
        $result = [];
        try {
            $apiurl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-recent-youtube-feeds?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
            $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
            if ($response['data']->code === 200) {
                $result['code'] = 200;
                $result['data'] = $response['data']->data;
                return $result;
            } else if ($response['data']->code === 400) {
                $result['code'] = 400;
                $result['message'] = $response['data']->error;
                return $result;
            } else {
                $result['code'] = 500;
                $result['message'] = 'Some error occured cant get feeds';
                return $result;
            }
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getTwitterFeeds() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we have to perform oepration from getting facebook pages feeds of particular facebook pages account by hitting facebook API.
     * This function is used to perform the operation of getting feeds from particular facebook pages account.
     * @param {integer} $accID-  account id of that particular facebook page account.
     * @param {integer} $type-  type of particular social account(facebook).
     * @return {object} Returns feeds of particular facebook acciount from facebook site  in json object format.
     * ! Do not change this function without referring API format of getting facebook feeds.
     */
    public function getFbPagesFeeds($accID, $type)
    {
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $pageId = 1;
        $result = [];
        try {
            if ($type === 1) {
                $apiUrl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-recent-feeds?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
            } else {
                $apiUrl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-recent-page-feeds?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
            }
            $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
            if ($response['data']->code === 200) {
                $result['code'] = 200;
                $result['data'] = $response['data']->data;
                return $result;
            } else if ($response['data']->code === 400) {
                $result['code'] = 400;
                $result['message'] = $response['data']->error;
                return $result;
            } else {
                $result['code'] = 500;
                $result['message'] = 'Some error occured cant get feeds';
                return $result;
            }
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getTwitterFeeds() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    public function getInstagramFeeds($accID)
    {
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $pageId = 1;
        $result = [];
        try {
            $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-insta-feeds?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
            $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
            if ($response['data']->code === 200) {
                $result['code'] = 200;
                $result['data'] = $response['data']->data;
                return $result;
            } else if ($response['data']->code === 400) {
                $result['code'] = 400;
                $result['message'] = $response['data']->error;
                return $result;
            } else {
                $result['code'] = 500;
                $result['message'] = 'Some error occured cant get feeds';
                return $result;
            }
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getTwitterFeeds() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to get  the next  feeds of twitter account on pagination.
     * This function is used for getting next feeds from particular twitter account,on pagination.
     * @param {integer} accid- account id of that particular twitter account.
     * @param {integer} pageId- page id pagination.
     * @return {object} Returns feeds of particular twitter acciount from twitter site  in json object format.
     * !Do not change this function without referring API format of getting the twitter feeds.
     */
    public function getNextTwitterFeeds(Request $request)
    {
        $accID = $request->accid;
        $pageId = $request->pageId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $result = [];
        try {
            $apiUrl1 = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/fetch-all-tweets?accountId=' . $accID . '&teamId=' . $teamID;
            $response2 = $this->helper->postApiCallWithAuth('put', $apiUrl1);
            if ($response2['data']->code === 200) {
                $apiurl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-tweets?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
                $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
                return $this->helper->responseHandlerWithArray($response);
            } else {
                $this->helper->logException(171, 500, $response2['data']->error, 'getTwitterFeeds() {FeedsController}');
                $result['data']['code'] = 500;
                $result['message'] = $response2['data']->error;
                return $result;
            }

        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getNextTwitterFeeds() {FeedsController}');
            $result['data']['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to dislike the particular feed of twitter.
     * This function is used for disliking  the particular feed of twitter.
     * @param {integer} twitterID - twitterID of particular post on twitterfeed.
     * @param {integer} accounId - accounId of twitter account.
     * @return {object} Returns in object form if twitter feed has been disliked or not.
     * ! Do not change this function without referring API format of disliking feed.
     */
    public function disLikeTheTweet(Request $request)
    {
        $tweetId = (int)$request->twitterID;
        $accounId = (int)$request->accounId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        try {
            $apiurl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/twitter-dislike?accountId=' . $accounId . '&teamId=' . $teamID . '&tweetId=' . $tweetId;
            $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
            return $this->helper->responseHandler($response['data']);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'disLikeTheTweet() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant dislike feeds';
            return $result;
        }

    }

    /**
     * TODO we've to like the particular feed of twitter.
     * This function is used for liking  the particular feed of twitter account from socioboard by hitting API.
     * @param {integer} twitterID - twitterID of feed.
     * @param {integer} accounId - accounId of twitter account.
     * @return {object} Returns in object form if twitter feed has been liked or not.
     * ! Do not change this function without referring API format of liking feed.
     */
    public function LikeTheTweet(Request $request)
    {
        $tweetId = (int)$request->twitterID;
        $accounId = (int)$request->accounId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        try {
            $apiurl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/twitter-like?accountId=' . $accounId . '&teamId=' . $teamID . '&tweetId=' . $tweetId;
            $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
            return $this->helper->responseHandler($response['data']);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'LikeTheTweet() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to get  comment on the particular twitter feed of twitter account from sociobaord.
     * This function is used for commenting on particular twitter feed of the twitter account by hitting API.
     * @param {integer} twitterID - twitterID of feed.
     * @param {integer} accounId - accounId of twitter account.
     * @return {object} Returns in object form if twitter feed has been commented  or not.
     * ! Do not change this function without referring API format of commenting on particular twitter feed.
     */
    public function commentOnTweet(Request $request)
    {
        $tweetId = (int)$request->twitterID;
        $accounId = (int)$request->accounId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $comment = $request->comment;
        $username = $request->userName;
        try {
            $apiurl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/twitter-comment?accountId=' . $accounId . '&teamId=' . $teamID . '&tweetId=' . $tweetId . '&comment=' . $comment . '&username=' . $username;
            $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
            return $this->helper->responseHandler($response['data']);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'LikeTheTweet() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to get  the next  feeds of youtube account on pagination.
     * This function is used for getting next feeds from particular youtube account,on pagination.
     * @param {integer} accid- account id of that particular youtube account.
     * @param {integer} pageId- page id pagination.
     * @return {object} Returns feeds of particular youtube channel acciount from youtube channel site  in json object format.
     * ! Do not change this function without referring API format of getting the youtube feeds.
     */
    public function getNextYoutubeFeeds(Request $request)
    {
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $accID = $request->accid;
        $pageId = $request->pageid;
        $result = [];
        try {
            $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-youtube-feeds?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
            $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
            if ($response['data']->code === 200) {
                $result['code'] = 200;
                $result['data'] = $response['data']->data;
                return $result;
            } else if ($response['data']->code === 400) {
                $result['code'] = 400;
                $result['message'] = $response['data']->error;
                return $result;
            } else {
                $result['code'] = 500;
                $result['message'] = 'Some error occured cant get feeds';
                return $result;
            }
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getTwitterFeeds() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to like the particular feed of youtube channel.
     * This function is used for liking  the particular feed of youtube channel account from socioboard by hitting API.
     * @param {integer} videoId - video ID of feed.
     * @param {integer} accounId- account id of that particular youtube account.
     * @return {object} Returns if feed has been liked or not in json format.
     * !Do not change this function without referring API format of liking youtube  feed.
     */
    public function likeYoutubeFeed(Request $request)
    {
        $tweetId = $request->videoId;
        $accounId = (int)$request->accounId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        try {
            $apiurl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/youtube-like-dislike?accountId=' . $accounId . '&teamId=' . $teamID . '&videoId=' . $tweetId . '&rating=like';
            $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
            return $this->helper->responseHandler($response['data']);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'disLikeTheTweet() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to disLike the particular feed of youtube channel.
     * This function is used for disLiking  the particular feed of youtube channel account from socioboard by hitting API.
     * @param {integer} videoId - video ID of feed.
     * @param {integer} accounId- account id of that particular youtube account.
     * @return {object} Returns if feed has been disliked or not in json format.
     * ! Do not change this function without referring API format of liking youtube  feed.
     */
    public function disLikeYoutubeFeed(Request $request)
    {
        $tweetId = $request->videoId;
        $accounId = (int)$request->accounId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        try {
             $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/youtube-like-dislike?accountId=' . $accounId . '&teamId=' . $teamID . '&videoId=' . $tweetId . '&rating=dislike';
            $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
            return $this->helper->responseHandler($response['data']);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'disLikeTheTweet() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to comment on the particular feed of youtube channel.
     * * This function is used for comment on  the particular feed of youtube channel account from socioboard by hitting API.
     * @param {integer} videoId - video ID of feed.
     * @param {integer} accounId- account id of that particular youtube account.
     * @return {object} Returns if feed has been commented or not in json format.
     * ! Do not change this function without referring API format of commenting on particular youtube  feed.
     */
    public function commentOnYoutubeFeed(Request $request)
    {
        $tweetId = $request->videoId;
        $accounId = (int)$request->accounId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $comment = $request->comment;
        try {
            $apiurl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/youtube-comment?accountId=' . $accounId . '&teamId=' . $teamID . '&videoId=' . $tweetId . '&comment=' . $comment;
            $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
            return $this->helper->responseHandler($response['data']);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'LikeTheTweet() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to like on the particular feed of facebook  account.
     * This function is used for liking on  the particular feed of facebook website account from socioboard by hitting API.
     * @param {integer} postID - post ID of feed.
     * @param {integer} accounId- account id of that particular facebook account.
     * @return {object} Returns if feed has been liked  or not in json format.
     * ! Do not change this function without referring API format of liking on particular facebook page  feed.
     */
    public function likeFbFeed(Request $request)
    {
        $postId = $request->postID;
        $accounId = (int)$request->accounId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        try {
            $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/facebook-like?accountId=' . $accounId . '&teamId=' . $teamID . '&postId=' . $postId;
            $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
            return $this->helper->responseHandler($response['data']);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'LikeTheTweet() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to comment on the particular feed of Facebook page account.
     * This function is used for comment on  the particular feed post  of Facebook page account from socioboard by hitting API.
     * @param {integer} postID - postID  of feed.
     * @param {integer} accounId- account id of that particular youtube account.
     * @return {object} Returns if feed has been commented or not in json format.
     * ! Do not change this function without referring API format of commenting on particular Facebook page account.
     */
    public function commentFbFeed(Request $request)
    {
        $postId = (int)$request->postID;
        $accounId = (int)$request->accounId;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $comment = $request->comment;
        try {
            $apiurl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/facebook-comment?accountId=' . $accounId . '&teamId=' . $teamID . '&postId=' . $postId . '&comment=' . $comment;
            $response = $this->helper->postApiCallWithAuth('post', $apiUrl);
            return $this->helper->responseHandler($response['data']);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'LikeTheTweet() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }

    /**
     * TODO we've to get  the next  feeds of facebook account on pagination.
     * This function is used for getting next feeds from particular facebookaccount,on pagination.
     * @param {integer} accid- account id of that particular facebook account.
     * @param {integer} pageId- page id pagination.
     * @param {integer} acctype- account type of social account.
     * @return {object} Returns feeds of particular particular facebookaccount from faecbook account   in json object format.
     * ! Do not change this function without referring API format of getting the facebook feeds.
     */
    public function getNextFacebookFeeds(Request $request)
    {
        $accID = $request->accid;
        $pageId = $request->pageId;
        $Acctype = $request->acctype;
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $result = [];
        try {
            if ($Acctype === 1) {
                $apiUrl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-recent-feeds' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;

            } else {
                $apiUrl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-recent-page-feeds?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
            }
            $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
            return $this->helper->responseHandlerWithArray($response);
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getNextTwitterFeeds() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }
    /**
     * TODO we've to get  the next  feeds of instagram account on pagination.
     * This function is used for getting next feeds from particular instagram,on pagination.
     * @param {integer} accid- account id of that particular instagram account.
     * @param {integer} pageId- page id pagination.
     * @return {object} Returns feeds of particular particular instagram acoount from instagram dev API  in json object format.
     * ! Do not change this function without referring API format of getting the instagram feeds.
     */
    function getNextInstagramFeeds(Request $request)
    {
        $team = Session::get('team');
        $teamID = $team['teamid'];
        $accID = $request->accid;
        $pageId = $request->pageid;
        $result = [];
        try {
            $apiurl = $apiUrl = $this->API_URL_FEEDS . env('API_VERSION') . '/feeds/get-insta-feeds?accountId=' . $accID . '&teamId=' . $teamID . '&pageId=' . $pageId;
            $response = $this->helper->postApiCallWithAuth('get', $apiUrl);
            if ($response['data']->code === 200) {
                $result['code'] = 200;
                $result['data'] = $response['data']->data;
                return $result;
            } else if ($response['data']->code === 400) {
                $result['code'] = 400;
                $result['message'] = $response['data']->error;
                return $result;
            } else {
                $result['code'] = 500;
                $result['message'] = 'Some error occured cant get feeds';
                return $result;
            }
        } catch (Exception $e) {
            $this->helper->logException($e->getLine(), $e->getCode(), $e->getMessage(), 'getTwitterFeeds() {FeedsController}');
            $result['code'] = 500;
            $result['message'] = 'Some error occured cant get feeds';
            return $result;
        }
    }
}
