<?php

namespace workingconcept\cloudflare\services;

use workingconcept\cloudflare\Cloudflare;
use workingconcept\cloudflare\records\RuleRecord;

use Craft;
use craft\base\Component;
use craft\helpers\UrlHelper;
use craft\helpers\Json;
use craft\errors\SiteNotFoundException;
use yii\base\NotSupportedException;
use yii\base\Exception;

/**
 * Provides a Cloudflare page rule service
 *
 * @package workingconcept\cloudflare
 */
class Rules extends Component
{
    /**
     * Returns all rules for a table.
     * @return array
     */
    public function getRulesForTable(): array
    {
        $data  = [];
        $rules = $this->getRules();

        foreach ($rules as $rule) {
            $data[(string)$rule->id] = [
                0 => $rule->trigger,
                1 => implode("\n", Json::decode($rule->urlsToClear))
            ];
        }

        return $data;
    }

    /**
     * @return array|null
     */
    public function getRules(): ?array
    {
        return RuleRecord::find()->all();
    }

    /**
     * Get supplied rules from the CP view and save them to the database.
     *
     * @return void
     * @throws SiteNotFoundException
     */
    public function saveRules(): void
    {
        RuleRecord::deleteAll();

        $request = Craft::$app->getRequest();
        $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;

        if ($rulesFromPost = $request->getBodyParam('rules')) {
            foreach ($rulesFromPost as [$trigger, $urlsToClear]) {
                $individualUrls = explode("\n", $urlsToClear);
                $individualUrls = array_map('trim', $individualUrls);

                $ruleRecord = new RuleRecord();
                $ruleRecord->siteId = $currentSiteId;
                $ruleRecord->trigger = trim($trigger);
                $ruleRecord->urlsToClear = Json::encode($individualUrls);

                $ruleRecord->save(false);
            }
        }
    }

    /**
     * @param string $url
     *
     * @return void
     * @throws Exception
     */
    public function purgeCachesForUrl(string $url): void
    {
        // max limit for Cloudflare API
        $cloudflareRuleCountLimit = 30;
        $relatedRules = $this->getRulesForUrl($url);
        $urlsToPurge = [];

        foreach ($relatedRules as $rule) {
            $clearList = Json::decode($rule->urlsToClear);

            foreach ($clearList as $clearUrl) {
                $urlsToPurge[] = UrlHelper::siteUrl($clearUrl);
            }
        }

        $numRules = count($urlsToPurge);

        if ($numRules > $cloudflareRuleCountLimit) {
            throw new NotSupportedException(
                sprintf(
                    'Too many rules; API requests are limited to %d and you provided %d',
                    $cloudflareRuleCountLimit,
                    $numRules
                )
            );
        }

        Cloudflare::getInstance()->api->purgeUrls($urlsToPurge);
    }

    /**
     * Get any rules that match a supplied URL.
     *
     * @param  string $url URL from Craft event that needs to be checked
     *
     * @return array       rules related to URL
     */
    public function getRulesForUrl(string $url): array
    {
        $relatedRules = [];

        if ($rules = $this->getRules())
        {
            foreach ($rules as $rule)
            {
                if (preg_match("`" . $rule->trigger . "`", $url))
                {
                    $relatedRules[] = $rule;
                }
            }
        }

        return $relatedRules;
    }
}
