<?php

namespace Chopsol\ContaoMatomoTracker\EventListener;

use BugBuster\BotDetection\ModuleBotDetection;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Environment;
use Contao\PageModel;
use Contao\System;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

class MatomoTracking
{
    private ?SessionInterface $session = null;
    private array $sessionData = [];

    public function __construct(private ContaoFramework|ContaoFrameworkInterface $framework)
    {
    }

    public function onKernelRequest(RequestEvent $requestEvent)
    {
        $logger = System::getContainer()?->get('monolog.logger.contao');
        $request = $requestEvent->getRequest();

        // Ist das Framework oder das pageModel nicht vorhanden (z.B. im Backend)
        // brauchen wir auch nichts tracken
        if (!$this->framework->isInitialized() || !$page = $request->attributes->get('pageModel')) {
            return;
        }

        if (true === is_int($page)) {
            $page = PageModel::findById($page);
        }

        if (!$page instanceof PageModel) {
            return;
        }

        if ($rootPage = PageModel::findByPk($page->rootId)) {
            $GLOBALS['COMATRACK_INIT'] = false;

            if (isset($rootPage->useComatrack) && '1' == $rootPage->useComatrack) {
                // Prüfung der Konfigurationsvariablen auf Existenz und Werte
                if ('' != $rootPage->comatrack_url && '' != $rootPage->comatrack_id) {
                    $GLOBALS['COMATRACK_INIT'] = true;
                    $GLOBALS['COMATRACK_SETTINGS'] = [
                        'url' => $rootPage->comatrack_url,
                        'id' => $rootPage->comatrack_id,
                        'token' => (isset($rootPage->comatrack_token) ? $rootPage->comatrack_token : ''),
                        'debug' => (isset($rootPage->comatrack_debug) && '1' == $rootPage->comatrack_debug ? true : false),
                        '404' => (isset($rootPage->comatrack_404) && '1' == $rootPage->comatrack_404 ? true : false),
                        'is_404' => ('error_404' == $page->type ? true : false),
                        'dnt' => (isset($rootPage->comatrack_dnt) && '1' == $rootPage->comatrack_dnt ? true : false),
                        'dnt_dim' => (isset($rootPage->comatrack_dim_dnt) ? $rootPage->comatrack_dim_dnt : ''),
                        'ip' => (isset($rootPage->comatrack_ip) && '1' == $rootPage->comatrack_ip ? true : false),
                    ];
                } else {
                    // Debug-Meldung das etwas nicht stimmt
                    if (isset($rootPage->comatrack_debug) && '1' == $rootPage->comatrack_debug) {
                        $logger->log('info','Background-Tracking: URL / ID fehlt', __METHOD__, TL_GENERAL);
                    }

                    return;
                }

                if ('' == trim($_SERVER['HTTP_USER_AGENT'])) {
                    if (isset($rootPage->comatrack_debug) && '1' == $rootPage->comatrack_debug) {
                        $logger->log('info','Background-Tracking: UserAgent nicht gesetzt, Ausschluss als BOT', __METHOD__, TL_GENERAL);
                    }
                    $GLOBALS['COMATRACK_INIT'] = false;

                    return;
                }

                // Sessiondaten laden sofern eine Session zuvor initialisiert wurde
                if ($request->hasPreviousSession()) {
                    try {
                        $session = $request->getSession();
                        $this->sessionData = $session->all();
                        // Ist der Nutzer im Backend angemeldet, tracken wir im Frontend nichts.
                        if (isset($this->sessionData['_security_contao_backend'])) {
                            if (isset($rootPage->comatrack_debug) && '1' == $rootPage->comatrack_debug) {
                                $logger->log('info', 'Background-Tracking: User im Backend angemeldet', __METHOD__, TL_GENERAL);
                            }
                            $GLOBALS['COMATRACK_INIT'] = false;

                            return;
                        }
                    }
                    catch (SessionNotFoundException $e) {
                        if (isset($rootPage->comatrack_debug) && '1' == $rootPage->comatrack_debug) {
                            $logger->log('info','Background-Tracking: Session has not been set', __METHOD__, TL_GENERAL);
                        }
                    }
                }

                // Bot-Erkennung - Bots sollen nicht getrackt werden
                // Um die Bot-Detection nicht jedes mal bei einer Nutzer-Session durchzuführen
                // zu müssen setzen wir einen Wert im Session-Cookie
                if (!isset($this->sessionData['comatrackIsBot'])) {
                    // IP-Ausschlussliste prüfen
                    $exludeIPs = Config::get('comatrack_exclude_ip');
                    if (strlen($exludeIPs) > 0) {
                        $exludeIPs = explode('~~~', $exludeIPs);
                        if (count($exludeIPs) > 0) {
                            $realIP = \Chopsol\ContaoMatomoTracker\IpCheck::getUserIP();
                            foreach ($exludeIPs as $exludeIP) {
                                if (preg_match('/:/', $exludeIP) && preg_match('/:/', $realIP)) {
                                    if (\Chopsol\ContaoMatomoTracker\IpCheck::IPv6InRange($realIP, $exludeIP)) {
                                        if (isset($rootPage->comatrack_debug) && '1' == $rootPage->comatrack_debug) {
                                            $logger->log('info','Background-Tracking: IP in Exclude-Liste ('.$realIP.' => '.$exludeIP.')', __METHOD__, TL_GENERAL);
                                        }
                                        $GLOBALS['COMATRACK_INIT'] = false;

                                        return;
                                    }
                                } elseif (preg_match("/\./", $exludeIP) && preg_match("/\./", $realIP)) {
                                    if (\Chopsol\ContaoMatomoTracker\IpCheck::IPv4InRange($realIP, $exludeIP)) {
                                        if (isset($rootPage->comatrack_debug) && '1' == $rootPage->comatrack_debug) {
                                            $logger->log('info','Background-Tracking: IP in Exclude-Liste ('.$realIP.' => '.$exludeIP.' )', __METHOD__, TL_GENERAL);
                                        }
                                        $GLOBALS['COMATRACK_INIT'] = false;

                                        return;
                                    }
                                }
                            }
                        }
                    }

                    // Ausschlussliste für UserAgents prüfen
                    $exlude_uas = Config::get('comatrack_exclude_ua');
                    if (strlen($exlude_uas) > 0) {
                        $exlude_uas = explode('~~~', $exlude_uas);
                        if (count($exlude_uas) > 0) {
                            foreach ($exlude_uas as $exlude_ua) {
                                //								$logger->log('info','Background-Tracking: Check UserAgent ('.$exlude_ua.')', __METHOD__, TL_GENERAL);
                                if ($_SERVER['HTTP_USER_AGENT'] === $exlude_ua) {
                                    if (isset($rootPage->comatrack_debug) && '1' == $rootPage->comatrack_debug) {
                                        $logger->log('info','Background-Tracking: UserAgent in Exclude-Liste', __METHOD__, TL_GENERAL);
                                    }
                                    $GLOBALS['COMATRACK_INIT'] = false;

                                    return;
                                }
                            }
                        }
                    }

                    $ModuleBotDetection = new ModuleBotDetection();
                    if ($ModuleBotDetection->checkBotAllTests()) {
                        $GLOBALS['COMATRACK_INIT'] = false;
                        $GLOBALS['COMATRACK_ISBOT'] = true;
                        $GLOBALS['COMATRACK_ISBOT2'] = false;

                        return;
                    }
                    // Zweite Ebene des Craweler Detectors
                    $CrawlerDetect = new CrawlerDetect();
                    // Check the user agent of the current 'visitor'
                    if ($CrawlerDetect->isCrawler()) {
                        $GLOBALS['COMATRACK_INIT'] = false;
                        $GLOBALS['COMATRACK_ISBOT'] = true;
                        $GLOBALS['COMATRACK_ISBOT2'] = true;

                        return;
                    }
                    if (!$this->session) {
                        try {
                            $this->session = $request->getSession();

                        } catch (SessionNotFoundException $e) {

                        }
                    }
                    if($this->session) {
                        $this->session->set('comatrackIsBot', false);
                    }
                } elseif (true == $this->sessionData['comatrackIsBot']) {
                    $GLOBALS['COMATRACK_INIT'] = false;
                    $GLOBALS['COMATRACK_ISBOT'] = true;
                    $GLOBALS['COMATRACK_ISBOT2'] = true;

                    return;
                }
            }
        }
    }

    public function onKernelTerminate(TerminateEvent $event)
    {
        $logger = System::getContainer()?->get('monolog.logger.contao');

        // Prozesslaufzeit fuer eventuelles Debugging bereitstellen
        $gentime = false;
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $gentime = ceil((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000);
        }

        // Sonderfall; war der Aufruf von einem Bot und haben wir Debugging aktiv,
        // dann loggen wir den Zugriff um die Prozesslaufzeit sehen zu können
        if (isset($GLOBALS['COMATRACK_ISBOT']) && true === $GLOBALS['COMATRACK_ISBOT'] && $GLOBALS['COMATRACK_SETTINGS']['debug']) {
            $logger?->log('info','Background-Tracking: Bot skipped'.($gentime ? ' / '.($gentime / 1000).'s' : ''), __METHOD__, TL_GENERAL);

            return;
        }
        // Zweite Bot-Detection separat loggen
        if (isset($GLOBALS['COMATRACK_ISBOT2']) && true === $GLOBALS['COMATRACK_ISBOT2'] && $GLOBALS['COMATRACK_SETTINGS']['debug']) {
            $logger?->log('info','Background-Tracking: Bot2 skipped'.($gentime ? ' / '.($gentime / 1000).'s' : ''), __METHOD__, TL_GENERAL);

            return;
        }

        // Ist das Tracking nicht initialisiert (z.B. wegen einem Zugriff auf dem Backend)
        // oder ist es inaktiv (z.B. auf Grund der Settings) brauchen wir nichts weiter zu tun
        if (!isset($GLOBALS['COMATRACK_INIT']) || false === $GLOBALS['COMATRACK_INIT']) {
            return;
        }

        // Soll eine 404 Seite nicht geloggt werden und handelt es sich um eine 404 Seite
        // Protokollieren wir das im Logging sofern aktiv, ansonsten brechen wir ab
        if (!$GLOBALS['COMATRACK_SETTINGS']['404'] && $GLOBALS['COMATRACK_SETTINGS']['is_404'] && (!isset($this->sessionData['comatrackIsBot']) || false === $this->sessionData['comatrackIsBot'])) {
            if ($GLOBALS['COMATRACK_SETTINGS']['debug']) {
                $logger?->log('info','Background-Tracking: 404 skipped'.($gentime ? ' / '.($gentime / 1000).'s' : ''), __METHOD__, TL_GENERAL);
            }

            return;
        }

        // Wenn Nutzer mit DNT Header nicht getrackt werden sollen, brechen wir ab...
        if ($GLOBALS['COMATRACK_SETTINGS']['dnt'] && isset($_SERVER['HTTP_DNT']) && '1' == $_SERVER['HTTP_DNT']) {
            if ($GLOBALS['COMATRACK_SETTINGS']['debug']) {
                $logger?->log('info','Background-Tracking: DoNotTrack skipped'.($gentime ? ' / '.($gentime / 1000).'s' : ''), __METHOD__, TL_GENERAL);
            }

            return;
        }

        // Tracking starten
        $matomoTracker = new \MatomoTracker($GLOBALS['COMATRACK_SETTINGS']['id'], $GLOBALS['COMATRACK_SETTINGS']['url']);

        // Zur Übergabe der IP-Adresse wird das Token benötigt; andernfalls werden alle
        // Zugriffe mit der IP-Adresse des Servers protokolliert. Aus Datenschutz-Sicht
        // ist es also sinnnvoll kein Token zu verwenden.
        if ('' != $GLOBALS['COMATRACK_SETTINGS']['token']) {
            $matomoTracker->setTokenAuth($GLOBALS['COMATRACK_SETTINGS']['token']);

            // Einstellung zum Tracking der IP-Adresse
            if ($GLOBALS['COMATRACK_SETTINGS']['ip']) {
                $matomoTracker->setIp(Environment::get('ip'));
            } else {
                $matomoTracker->setIp('127.0.0.1');
            }

            // GeoIP-Informationen übergeben sofern vom Server vorhanden
            if (isset($_SERVER['GEOIP_COUNTRY_CODE'])) {
                $matomoTracker->setCountry(strtolower($_SERVER['GEOIP_COUNTRY_CODE']));
            }
            if (isset($_SERVER['GEOIP_REGION'])) {
                $matomoTracker->setRegion($_SERVER['GEOIP_REGION']);
            }
            if (isset($_SERVER['GEOIP_CITY'])) {
                $matomoTracker->setCity($_SERVER['GEOIP_CITY']);
            }
            if (isset($_SERVER['GEOIP_LONGITUDE']) && isset($_SERVER['GEOIP_LATITUDE'])) {
                $matomoTracker->setLatitude($_SERVER['GEOIP_LATITUDE']);
                $matomoTracker->setLongitude($_SERVER['GEOIP_LONGITUDE']);
            }
        }

        // Bulk-Tracking wird verwendet um die Daten als POST an Matomo zu übergeben
        // damit keine Details des Trackings im Logfiles des Servers verbleiben
        $matomoTracker->enableBulkTracking();

        // Verwendung der Session-ID zur Erkennung einer Benutzer-Session
        // Da die ID in Matomo Hexadezimal sein muss, kodieren wir die Session-ID per MD5
        if (!$this->session) {
            $request = $event->getRequest();
            try {
                $this->session = $request->getSession();

            } catch (SessionNotFoundException $e) {

            }
        }
        if($this->session) {
            $matomoTracker->setVisitorId(substr(md5($this->session->getId()), 0, 16));
        }

        // Die aufgerufene URL bereitstellen
        $matomoTracker->setUrl(Environment::get('uri'));

        // Tracking der Laufzeit des Skriptes bis hier hin. Da dieser Request als eines der letzten
        // Aktionen in Symfony erfolgt, sollte diese Zeit recht aussagekräftig sein.
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $matomoTracker->setGenerationTime((int) $gentime);
        }

        // DoNotTrack in einem CustomDimension schreiben sofern konfiguriert
        if (isset($GLOBALS['COMATRACK_SETTINGS']['dnt_dim']) && (int) $GLOBALS['COMATRACK_SETTINGS']['dnt_dim'] > 0) {
            if (isset($_SERVER['HTTP_DNT']) && '1' == $_SERVER['HTTP_DNT']) {
                $matomoTracker->setCustomTrackingParameter('dimension'.(int) $GLOBALS['COMATRACK_SETTINGS']['dnt_dim'], '1');
            } else {
                $matomoTracker->setCustomTrackingParameter('dimension'.(int) $GLOBALS['COMATRACK_SETTINGS']['dnt_dim'], '0');
            }
        }

        // Den Pageview Tracken und den Titel der Seite mit übergeben
        $matomoTracker->doTrackPageView(html_entity_decode($GLOBALS['objPage']->pageTitle));

        // Für Debug-Zwecke werden die gesamten Aufrufe an Matomo geloggt - Debug sollte dementsprechend
        // immer deaktiviert sein
        if ($GLOBALS['COMATRACK_SETTINGS']['debug']) {
            foreach ($matomoTracker->storedTrackingActions as $log) {
                $logger?->log('info','Background-Tracking: '.$log, __METHOD__, TL_GENERAL);
            }
            $logger?->log('info','Background-Tracking: Laufzeit des Skripts: '.($gentime / 1000).'s', __METHOD__, TL_GENERAL);
        }

        // Übersenden der Tracking-Infos an Matomo
        $response = $matomoTracker->doBulkTrack();
        // Die Antwort ist normalerweise ein JSON dessen Status wir prüfen können
        if ($GLOBALS['COMATRACK_SETTINGS']['debug']) {
            if (!$json = json_decode($response, true) || !isset($json['status']) || 'success' != $json['status']) {
                $logger?->log('info','Background-Tracking fehlgeschlagen - Server-Antwort: '.$response, __METHOD__, TL_ERROR);
            }
        }
    }
}
