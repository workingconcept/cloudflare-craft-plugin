<?php
/**
 * Cloudflare plugin for Craft CMS 3.x
 *
 * Purge Cloudflare caches from Craft.
 *
 * @link      https://workingconcept.com
 * @copyright Copyright (c) 2017 Working Concept
 */

namespace workingconcept\cloudflare\controllers;

use workingconcept\cloudflare\Cloudflare;

use Craft;
use craft\web\Controller;
use craft\helpers\UrlHelper;
use yii\web\Response;
use yii\web\BadRequestHttpException;
use craft\errors\SiteNotFoundException;

/**
 * @author    Working Concept
 * @package   Cloudflare
 * @since     1.0.0
 */
class DefaultController extends Controller
{
    /**
     * Checks whether the supplied credentials can connect to the Cloudflare account.
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionVerifyConnection(): Response
    {
        $this->requireAcceptsJson();

        $wasSuccessful = Cloudflare::getInstance()->api->verifyConnection();
        $return = [
            'success' => $wasSuccessful
        ];

        if ( ! $wasSuccessful) {
            $return['errors'] = Cloudflare::getInstance()->api->getConnectionErrors();
        }

        return $this->asJson($return);
    }

    /**
     * Returns all available zones on the configured account.
     * @return Response
     */
    public function actionFetchZones(): Response
    {
        return $this->asJson(Cloudflare::getInstance()->api->getZones());
    }

    /**
     * Have Cloudflare purge URL caches passed via `urls` parameter,
     * a string with each item on its own line.
     *
     * @return mixed
     * @throws craft\errors\MissingComponentException without a valid session.
     * @throws BadRequestHttpException
     */
    public function actionPurgeUrls()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        $urls = $request->getBodyParam('urls');

        if (empty($urls))
        {
            $session->setError(Craft::t(
                'cloudflare',
                'Failed to purge empty or invalid URLs.'
            ));

            return $this->asErrorJson(
                'Failed to purge empty or invalid URLs.'
            );
        }

        // split lines into array items
        $urls = explode("\n", $urls);
        $response = Cloudflare::getInstance()->api->purgeUrls($urls);

        return $this->asJson($response);
    }

    /**
     * Purge entire Cloudflare zone cache.
     *
     * @return mixed
     * @throws BadRequestHttpException
     */
    public function actionPurgeAll()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $response = Cloudflare::getInstance()->api->purgeZoneCache();

        return $this->asJson($response);
    }

    /**
     * Save our Craft-URL-specific purge rules.
     *
     * @return mixed
     *
     * @throws craft\errors\MissingComponentException without a valid session
     * @throws SiteNotFoundException
     */
    public function actionSaveRules()
    {
        Cloudflare::getInstance()->rules->saveRules();

        Craft::$app->getSession()->setNotice(Craft::t(
            'cloudflare',
            'Cloudflare rules saved.'
        ));

        return $this->redirect(UrlHelper::cpUrl('cloudflare/rules'));
    }
}
