<?php

declare(strict_types=1);

// The classification of the pages saved from the real sites of the November 2026 study (tests/fixtures/crawls),
// against the system the study (docs/market/grid-web-systems-2026-11.md) gave them.
describe('saved pages of real grids', function () {
    test('a grid goes to the system of the study', function (string $slug, string $system) {
        expect(classifier()->classify(crawl($slug))['system'])->toBe($system);
    })->with([
        'w4os, flagship' => ['neverworldgrid-com', 'wordpress_w4os'],
        'w4os, on a http site' => ['kurtopia-ddns-net', 'wordpress_w4os'],
        'WordPress without w4os' => ['digiworldz-com', 'wordpress'],
        'DreamGrid bundle' => ['cavegrid-org-8002', 'dreamgrid'],
        'DreamGrid on the Outworldz host' => ['freelife-outworldz-net-8002-wifi', 'dreamgrid'],
        'jOpenSim on Joomla' => ['trianon-world-com', 'jopensim'],
        'Moodle with Modlos' => ['jogrid-net-wi', 'modlos'],
        'osWebInterface' => ['grid-2nd-sanctuary-de-welcomesplashpage-php', 'oswebinterface'],
        'Hosting4Opensim panel' => ['friends-grid-com', 'hosted'],
        'own code with its own sign-up' => ['osgrid-org', 'custom'],
        'framework with its own sign-up' => ['zetaworlds-com', 'other_cms_ui'],
        'Concrete CMS' => ['darkheartsos-com', 'other_cms'],
        'Grav' => ['4creative-net', 'other_cms'],
        'Google Sites' => ['twiztidtimez-com', 'builders'],
        'static page' => ['planet3l-com', 'custom_site'],
    ]);

    test('a grid that forbids crawling is in no system', function () {
        $result = classifier()->classify(crawl('astralia-eu'));

        expect($result['system'])->toBeNull()->and($result['outcome'])->toBe('robots');
    });

    test('the signals say what was seen', function () {
        expect(classifier()->classify(crawl('kurtopia-ddns-net'))['signals'])->toContain('w4os');
    });
});

describe('sites with no known system', function () {
    $crawl = fn (array $pages, string $status = 'ok') => ['status' => $status, 'note' => '', 'pages' => $pages];

    test('a page with a login form is the grid\'s own interface, without one it is a custom website', function () use ($crawl) {
        $login = $crawl([page('home', 'http://a.example/', '<form><input type="password" name="pw"></form>')]);
        $plain = $crawl([page('home', 'http://a.example/', '<h1>Welcome</h1><p>Join us in world</p>')]);

        expect(classifier()->classify($login)['system'])->toBe('custom')
            ->and(classifier()->classify($plain)['system'])->toBe('custom_site');
    });

    test('an error page for the w4os probe is not w4os, even if it repeats the address', function () use ($crawl) {
        $found = $crawl([
            page('home', 'http://a.example/', '<meta name="generator" content="WordPress 7.1.4"><link href="/wp-content/x.css">'),
            page('probe', 'http://a.example/wp-content/plugins/w4os/readme.txt', '<html>Not found: /wp-content/plugins/w4os/readme.txt <link href="/wp-content/plugins/w4os/x.css"></html>', status: 404),
        ]);

        expect(classifier()->classify($found)['system'])->toBe('wordpress');
    });

    test('WordPress is found by its uploads when the page has no generator and no stylesheet of its own', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example/', '<html><img src="http://a.example/wp-content/uploads/2025/06/x.png"></html>')]);

        expect(classifier()->classify($found)['system'])->toBe('wordpress');
    });

    test('Laravel is a CMS, not custom code', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example/', '<h1>Welcome</h1>', cookies: ['laravel_session'])]);

        expect(classifier()->classify($found)['system'])->toBe('other_cms');
    });

    test('Wix is a commercial web host', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example/', '<h1>Welcome</h1><meta name="generator" content="Wix.com Website Builder">')]);

        expect(classifier()->classify($found)['system'])->toBe('builders');
    });

    test('a grid port with no page is the grid service only', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example:8002/', '', headers: ['server' => 'OSWebServer'], status: 404)], 'error');

        expect(classifier()->classify($found)['system'])->toBe('grid_server');
    });

    test('the page of an unconfigured web server is no website', function () use ($crawl) {
        $found = $crawl([page('home', 'http://a.example/', '<title>Apache2 Ubuntu Default Page: It works</title>')]);
        $result = classifier()->classify($found);

        expect($result['system'])->toBeNull()->and($result['outcome'])->toBe('default_page');
    });

    test('a site that did not answer is in no system', function (string $status) use ($crawl) {
        $result = classifier()->classify($crawl([page('home', 'http://a.example/', '', status: 0)], $status));

        expect($result['system'])->toBeNull()->and($result['outcome'])->toBe($status);
    })->with(['unreachable', 'error']);
});

describe('the site and the grid info together', function () {
    $wp = fn () => page('home', 'http://a.example/', '<meta name="generator" content="WordPress 7.1.4"><link href="/wp-content/x.css">');
    $wifi = fn () => ['status' => 'ok', 'note' => '', 'info' => [], 'pages' => [
        ['key' => 'register', 'role' => 'published'] + page('published', 'http://a.example:8002/wifi/user/account/', '<link href="/wifi/fluid.css"><a href="/wifi/user/account/">x</a>'),
    ]];
    $wpLogin = fn () => ['status' => 'ok', 'note' => '', 'info' => [], 'pages' => [
        ['key' => 'register'] + page('published', 'http://a.example/wp-login.php?action=register', '<meta name="generator" content="WordPress 7.1.4"><link href="/wp-includes/x.css">'),
    ]];
    $site = fn (array $pages, string $status = 'ok') => ['status' => $status, 'note' => '', 'pages' => $pages];

    test('both say the same: high confidence', function () use ($wp, $wpLogin, $site) {
        $result = classifier()->classifyGrid($site([$wp()]) + ['grid' => $wpLogin()]);

        expect($result['system'])->toBe('wordpress')->and($result['confidence'])->toBe('high')->and($result['review'])->toBeNull();
    });

    test('the site is w4os, the grid info says WordPress: the same family, no review', function () use ($wpLogin, $site) {
        $home = page('home', 'http://a.example/', '<link href="/wp-content/plugins/w4os/v2/public/public.css?ver=2.9.4"><meta name="generator" content="WordPress 7.1.4">');
        $result = classifier()->classifyGrid($site([$home]) + ['grid' => $wpLogin()]);

        expect($result['system'])->toBe('wordpress_w4os')->and($result['review'])->toBeNull()->and($result['confidence'])->toBe('high');
    });

    test('the grid info recognises an OpenSim interface: it decides, the site is only its front', function () use ($wp, $wifi, $site) {
        $result = classifier()->classifyGrid($site([$wp()]) + ['grid' => $wifi()]);

        expect($result['system'])->toBe('diva_wifi')->and($result['decided_by'])->toBe('grid_info')
            ->and($result['by'])->toBe(['site' => 'wordpress', 'grid_info' => 'diva_wifi'])
            ->and($result['review'])->toBeNull()->and($result['confidence'])->toBe('medium');
    });

    test('the grid info only sees a plain CMS that differs from the site: the site stays, to review', function () use ($wpLogin, $site) {
        $wix = page('home', 'http://a.example/', '<h1>Welcome</h1><meta name="generator" content="Wix.com Website Builder">');
        $result = classifier()->classifyGrid($site([$wix]) + ['grid' => $wpLogin()]);

        expect($result['system'])->toBe('builders')->and($result['decided_by'])->toBe('site')->and($result['confidence'])->toBe('low')
            ->and($result['review'])->toBe('site says builders, grid info says wordpress');
    });

    test('both say the same: decided by both', function () use ($wp, $wpLogin, $site) {
        expect(classifier()->classifyGrid($site([$wp()]) + ['grid' => $wpLogin()])['decided_by'])->toBe('both');
    });

    test('the site has nothing known: the grid info decides', function () use ($wifi, $site) {
        $result = classifier()->classifyGrid($site([page('home', 'http://a.example/', '<h1>My grid</h1>')]) + ['grid' => $wifi()]);

        expect($result['system'])->toBe('diva_wifi')->and($result['by']['site'])->toBe('custom_site')->and($result['decided_by'])->toBe('grid_info');
    });

    test('the site does not answer, the grid info does: the grid is classified', function () use ($wifi, $site) {
        $result = classifier()->classifyGrid($site([page('home', 'http://a.example/', '', status: 0)], 'unreachable') + ['grid' => $wifi()]);

        expect($result['system'])->toBe('diva_wifi')->and($result['outcome'])->toBe('classified');
    });

    test('a site that forbids crawling stays out, whatever the grid info says', function () use ($wifi, $site) {
        $result = classifier()->classifyGrid($site([], 'robots') + ['grid' => $wifi()]);

        expect($result['system'])->toBeNull()->and($result['outcome'])->toBe('robots');
    });

    test('the signals say which side they come from', function () use ($wp, $wpLogin, $site) {
        $signals = classifier()->classifyGrid($site([$wp()]) + ['grid' => $wpLogin()])['signals'];

        expect($signals)->toContain('site: WordPress 7.1.4', 'grid info: WordPress 7.1.4');
    });

    test('a site that says it is not OpenSim is flagged, not excluded', function () use ($site) {
        $home = page('home', 'http://a.example/', '<h1>Welcome</h1><p>This world is NOT an OpenSim world, it runs Halcyon.</p>');
        $result = classifier()->classifyGrid($site([$home]));

        expect($result['flags'])->toBe(['says it is not OpenSim'])->and($result['outcome'])->toBe('classified');
    });
});

describe('the w4os family is one row', function () {
    test('the WordPress plugin, the Grav plugin and the mini site all go to w4os, the signal that decided is kept', function (string $html, string $signal) {
        $result = classifier()->classify(['status' => 'ok', 'note' => '', 'pages' => [page('home', 'http://a.example/', $html)]]);

        expect($result['system'])->toBe('wordpress_w4os')->and($result['w4os_signal'])->toBe($signal);
    })->with([
        'WordPress plugin' => ['<link href="/wp-content/plugins/w4os/v2/public/public.css">', 'w4os'],
        'Grav plugin' => ['<link href="/user/plugins/w4os/css/w4os.css"><meta name="generator" content="GravCMS">', 'w4os Grav plugin'],
        'mini site' => ['<div class="w4os-credits">Powered by</div>', 'w4os mini site'],
    ]);

    test('the helpers addresses of the grid info are kept in the result, not used to classify', function () {
        $helpers = ['status' => 'ok', 'note' => '', 'info' => ['economy' => 'http://a.example/helpers/', 'search' => 'http://a.example/helpers/ossearch.php'], 'pages' => []];
        $http = site(['http://a.example/' => ['body' => '<h1>A</h1>'], 'http://a.example:8002/get_grid_info' => ['body' => '<gridinfo><economy>http://a.example/helpers/</economy><search>http://a.example/helpers/ossearch.php</search></gridinfo>']]);
        $crawl = (new GridSurvey\Crawler($http))->crawl(['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']);
        $grids = (new GridSurvey\Survey(new GridSurvey\Crawler($http), classifier()))->run([['name' => 'A', 'url' => 'http://a.example/', 'login_uri' => 'a.example:8002']]);

        expect($crawl['grid']['info'])->toEqualCanonicalizing($helpers['info'])
            ->and($grids[0]['grid_info'])->toEqualCanonicalizing($helpers['info'])
            ->and($grids[0]['system'])->toBe('custom_site');
    });
});

// The three grids of the dry run whose site is a builder or WordPress and whose grid announces an OpenSim interface
describe('the interface the grid announces decides', function () {
    $site = fn (array $pages) => ['status' => 'ok', 'note' => '', 'pages' => $pages];
    $published = fn (string $url, string $html) => ['status' => 'ok', 'note' => '', 'info' => [], 'pages' => [['key' => 'register'] + page('published', $url, $html)]];

    test('Accueil Dracsoom: a site builder in front of a DreamGrid', function () use ($site, $published) {
        $home = page('home', 'http://dracsoom.free.fr/', '<meta name="generator" content="WebAcappella 4.6.26">');
        $grid = $published('http://dracsoom.ddns.net:8002/wifi/user/account/', '<link rel="stylesheet" href="/wifi/fluid.css"><p class="textlogo">Opensim DreamGrid Virtual world</p>');
        $result = classifier()->classifyGrid($site([$home]) + ['grid' => $grid]);

        expect($result['system'])->toBe('dreamgrid')->and($result['by'])->toBe(['site' => 'builders', 'grid_info' => 'dreamgrid']);
    });

    test('OpenSim Network: WordPress in front of a Diva Wifi', function () use ($site, $published) {
        $home = page('home', 'https://opensimnetwork.com/', '<img src="https://opensimnetwork.com/wp-content/uploads/2025/06/x.png">');
        $grid = $published('https://opensimnetwork.zapto.org/wifi/user/account/', '<link rel="stylesheet" href="/wifi/fluid.css"><a href="/wifi/user/account/">Create</a>');
        $result = classifier()->classifyGrid($site([$home]) + ['grid' => $grid]);

        expect($result['system'])->toBe('diva_wifi')->and($result['by']['site'])->toBe('wordpress');
    });

    test('Twiztid Timez: Google Sites in front of a Hosting4Opensim panel', function () use ($site, $published) {
        $home = classifier()->classifyGrid(crawl('twiztidtimez-com'));
        $grid = $published('https://panel.twiztidtimez.com/welcome', '<button id="h4os-theme-toggle" data-h4os-theme-toggle></button><script>var K = "h4os:theme";</script>');
        $result = classifier()->classifyGrid(['grid' => $grid] + crawl('twiztidtimez-com'));

        expect($home['system'])->toBe('builders')
            ->and($result['system'])->toBe('hosted')->and($result['by'])->toBe(['site' => 'builders', 'grid_info' => 'hosted']);
    });

    test('Japan Open Grid, the counter-case: the grid info only sees Moodle, the site keeps the hand and stays Modlos', function () use ($published) {
        $grid = $published('https://www.jogrid.net/wi/login/index.php', '<html><body class="moodle"><script>M.cfg = {};</script></body></html>');
        $grid['pages'][0]['cookies'] = ['MoodleSession'];
        $result = classifier()->classifyGrid(['grid' => $grid] + crawl('jogrid-net-wi'));

        expect($result['by']['grid_info'])->toBe('other_cms')
            ->and($result['system'])->toBe('modlos')->and($result['decided_by'])->toBe('both')->and($result['review'])->toBeNull();
    });

    test('the grid info recognises nothing: the site decides', function () use ($site, $published) {
        $grid = $published('http://a.example/signup', '<h1>Sign up</h1>');
        $result = classifier()->classifyGrid($site([page('home', 'http://a.example/', '<meta name="generator" content="WordPress 7.1.4"><link href="/wp-content/x.css">')]) + ['grid' => $grid]);

        expect($result['system'])->toBe('wordpress')->and($result['decided_by'])->toBe('site')->and($result['by']['grid_info'])->toBeNull();
    });
});
