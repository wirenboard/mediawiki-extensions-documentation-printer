<?php
namespace MediaWiki\Extension\WbPrint;

require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
$dotenv->load();

use Article;

use Wikimedia\Rdbms\DBConnRef;

class SpecialPrint extends \SpecialPage {

    /**
     * @var DBConnRef
     */
    private $db;

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		parent::__construct( 'Print' );

		$this->db = wfGetDB(DB_REPLICA);
	}

    /**
     * Shows the page to the user.
     * @param string $sub The subpage string argument (if any).
     * @throws \MWException
     */
	public function execute( $sub ) {
        global $wgRequest;

		$pageTitle = $wgRequest->getText('page');
		if (!$pageTitle) {
            return $this->executeList();
        }
		return $this->executePage($pageTitle);
	}

    /**
     * Render queue list
     */
	private function executeList() {
        global $wgRequest;

        $cacheDir = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..').DIRECTORY_SEPARATOR.$_ENV['CACHE_DIR'];
        $urlDir = $_ENV['DOMAIN'].'/wiki/'.$_ENV['CACHE_DIR'];

        $out = $this->getOutput();

        $page = $this->getPage([
            'page_title' => 'Print_list',
        ]);
        if (!$page) {
            $out->setPageTitle($this->msg( 'error-title'));
            $out->addWikiMsg('error-print-list-page-not-found');
        }

        $page = Article::newFromID($page->page_id);

        $pageLinks = $page->getParserOutput()->mLinks;

        if ($pageLinks && is_array($pageLinks)) {
            $links = array_keys(current($pageLinks));
            $tmpLinks = [];
            foreach ($links as $name) {
                $page = $this->getPage([
                    'page_title' => $name,
                ]);
                if (!$page) {
                    continue;
                }

                $page = Article::newFromID($page->page_id);
                $link = '<a href="/wiki/'.$page->getTitle()->mUrlform.'" target="_blank">'.$page->getTitle()->mTextform.'</a>';

                $fileName = $name.'.pdf';
                $filePath = $cacheDir.DIRECTORY_SEPARATOR.$fileName;
                $fileUrl = $urlDir.'/'.$fileName;
                if (file_exists($filePath)) {
                    $fileTime = filemtime($filePath);
                    $link.= ' (<a href="'.$fileUrl.'">'.date('d-m-Y H:i', $fileTime).'</a>)';
                }

                $tmpLinks[] = $link;
            }

            $out->addHTML(implode('<br>', $tmpLinks));
        }
    }

    /**
     * Render page
     * @param string $pageTitle
     * @throws \MWException
     */
	private function executePage($pageTitle) {
        global $wgRequest;

        $out = $this->getOutput();

        $page = $this->getPage([
            'page_title' => $pageTitle,
        ]);
        if (!$page) {
            $out->setPageTitle($this->msg( 'error-title'));
            $out->addWikiMsg('error-page-not-found');
        }

        $page = Article::newFromID($page->page_id);
        $pageText = $this->cleanText($page->getParserOutput()->getText());
        $pageLinks = $this->getPageLinksFromText($pageText);

        $menuLinks = [
            [
                'title' => $page->getTitle()->getText(),
                'url' => '#firstHeading',
            ]
        ];
        if ($pageLinks) {
            foreach ($pageLinks as $pageLink) {
                if ($pageLink->getId() !== $page->getId()) {
                    $menuLinks[] = [
                        'title' => $this->getPageLinkTitle($pageLink),
                        'url' => '#'.$pageLink->getTitle()->mUrlform,
                    ];
                    $pageText = str_replace('/wiki/'.$pageLink->getTitle()->mUrlform, '#'.$pageLink->getTitle()->mUrlform, $pageText);
                }
            }
        }

        $num = 1;
        $menuHTML = '<div>';
        $menuHTML.= '<h1>Содержание</h1>';
        $menuHTML.= '<div class="toc" style="margin-top:10px;width: calc(100% - 14px);">';
        $menuHTML.= '<ul>';
        foreach ($menuLinks as $link) {
            $menuHTML.= '<li class="toclevel-1 tocsection-1">';
            $menuHTML.= '<a href="'.$link['url'].'">';
            $menuHTML.= '<span class="tocnumber">'.$num++.'</span>';
            $menuHTML.= '<span class="toctext">'.$link['title'].'</span>';
            $menuHTML.= '</a>';
            $menuHTML.= '</li>';
        }
        $menuHTML.= '</ul>';
        $menuHTML.= '</div>';
        $menuHTML.= '</div>';

        $cssHTML = '<style>';
        $cssHTML.= '@media print { @page { size: auto; margin-top: 6mm; margin-bottom: 6mm; }  }';
        $cssHTML.= '.page-break { page-break-before: always; }';
        $cssHTML.= '.title-page { height: calc(100vh - 100px); page-break-after: always; }';
        $cssHTML.= '.title-page-content { display: flex; flex-direction: column; flex-wrap: wrap; justify-content: center; height: 100%; }';
        $cssHTML.= '.row { display: flex; flex-wrap: wrap; }';
        $cssHTML.= '.col { flex-basis: 0; flex-grow: 1; max-width: 100%; }';
        $cssHTML.= '.col-auto { flex: 0 0 auto; width: auto; max-width: 100%; }';
        $cssHTML.= '</style>';
        $out->addHTML($cssHTML);

        $titleHTML = '<h1 id="firstHeading" class="firstHeading">'.$page->getTitle()->getText().'</h1>';

        $currentUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/wiki/'.$wgRequest->getText('page');

        $titlePageHTML = '<div class="title-page">';
        $titlePageHTML.= '    <div class="row" style="flex: 0 0 auto;">';
        $titlePageHTML.= '        <div class="col"><svg fill="#5cb300" width="160" height="40" viewBox="0 0 160 40"><g stroke="none" stroke-width="1" fill-rule="evenodd"><g transform="translate(-136.000000, -40.000000)"><path d="M216.000258,78.726265 C185.97354,78.726265 170.26441,80 151.833191,80 C139.373922,80 136,71.0458791 136,60 C136,48.9546341 139.373922,40 151.833191,40 C170.26441,40 185.97354,41.2742482 216.000258,41.2742482 C246.027494,41.2742482 261.73559,40 280.167326,40 C292.625561,40 296,48.9546341 296,60 C296,71.0458791 292.625561,80 280.167326,80 C261.73559,80 246.027494,78.726265 216.000258,78.726265 Z M163.664869,68.2926203 L168.370372,52.7763523 L163.34438,52.7763523 L161.398702,60.8236683 L158.697807,52.7763523 L155.385398,52.7763523 L152.683469,60.8236683 L150.737273,52.7763523 L145.711799,52.7763523 L150.418335,68.2926203 L154.223884,68.2926203 L157.041085,59.785487 L159.859321,68.2926203 L163.664869,68.2926203 Z M169.593554,68.2926203 L174.357985,68.2926203 L174.357985,52.7763523 L169.593554,52.7763523 L169.593554,68.2926203 Z M191.382878,62.064046 L200.736504,62.064046 L200.736504,59.9584317 C200.736504,55.546033 197.917752,52.6028944 193.73537,52.6028944 C189.407218,52.6028944 186.705289,55.6615006 186.705289,60.5352561 C186.705289,65.8421431 189.173571,68.465565 194.141151,68.465565 C195.507881,68.465565 196.610467,68.264395 197.570383,67.8605152 C198.528232,67.4853741 199.079783,67.0814944 200.214417,66.0140614 L197.366201,63.1889562 C196.756238,63.7945191 196.46573,63.9956892 195.972074,64.1983989 C195.507881,64.3708303 194.897918,64.4857847 194.286922,64.4857847 C192.486497,64.4857847 191.46972,63.6210613 191.382878,62.064046 Z M193.706423,56.4990249 C195.129498,56.4990249 196.000504,57.3914605 196.029968,58.9207636 L191.382878,58.9207636 C191.382878,57.4212255 192.282832,56.4990249 193.706423,56.4990249 Z M186.645999,52.9492969 C186.064466,52.7204147 185.368695,52.6028944 184.641908,52.6028944 C183.160422,52.6028944 181.969444,53.066304 180.864274,54.0464949 L180.864274,52.7763523 L176.2177,52.7763523 L175.054635,52.7763523 C175.054635,52.7763523 176.2177,53.7000924 176.2177,55.6984502 L176.2177,68.4373396 L180.981614,68.4373396 L180.981614,59.005953 C180.981614,57.7086113 181.736314,56.8726265 182.928326,56.8726265 C183.567753,56.8726265 183.973534,57.0737966 184.612961,57.6798727 L188.214844,54.0464949 C187.517005,53.4116802 187.198584,53.180232 186.645999,52.9492969 Z M171.962071,47.0729242 C170.701826,47.0729242 169.693319,48.0741558 169.693319,49.3253105 C169.693319,50.6052037 170.701826,51.6059222 171.962071,51.6059222 C173.222317,51.6059222 174.25822,50.6052037 174.25822,49.3253105 C174.25822,48.0741558 173.222317,47.0729242 171.962071,47.0729242 Z M210.236936,52.602997 C209.510666,52.602997 208.812827,52.7487427 208.146003,53.0084163 C207.506576,53.2670635 207.215551,53.46926 206.547694,54.0465976 L206.547694,52.7759417 L201.899569,52.7759417 L200.736504,52.7759417 C200.736504,52.7759417 201.899569,53.700195 201.899569,55.6985528 L201.899569,68.292723 L206.664,68.292723 L206.664,59.0060556 C206.664,57.7087139 207.419734,56.8722159 208.580214,56.8722159 C209.743279,56.8722159 210.497979,57.7087139 210.497979,59.0060556 L210.497979,68.292723 L215.263961,68.292723 L215.263961,58.3722673 C215.263961,54.7671149 213.374626,52.602997 210.236936,52.602997 Z M271.420509,52.9492969 C270.840011,52.7204147 270.143206,52.6028944 269.415902,52.6028944 C267.93545,52.6028944 266.743438,53.066304 265.639818,54.0464949 L265.639818,52.7763523 L260.992728,52.7763523 L259.83018,52.7763523 C259.83018,52.7763523 260.992728,53.7000924 260.992728,55.6984502 L260.992728,68.2926203 L265.756125,68.2926203 L265.756125,59.005953 C265.756125,57.7086113 266.511341,56.8726265 267.701803,56.8726265 C268.34123,56.8726265 268.749078,57.0737966 269.387472,57.6798727 L272.990389,54.0464949 C272.29255,53.4116802 271.972061,53.180232 271.420509,52.9492969 Z M225.209148,52.7582367 C224.604871,52.7582367 223.855341,52.6448219 223.001909,52.7438674 C222.486542,52.7715796 221.986683,52.8316227 221.516805,52.9445243 L220.597208,48.9195833 C220.277236,47.6273735 218.963231,46.8365493 217.661115,47.1531869 C216.358999,47.4703377 215.563463,48.7753772 215.883435,50.0686134 L216.721359,53.2498717 C216.721359,53.2498717 216.914686,54.0401827 216.914686,55.7106127 L216.910551,55.7131787 L216.910551,68.3330083 L221.544201,68.3330083 L221.544201,67.3697526 C222.922821,68.114903 224.144298,68.4572 225.209148,68.4572 C228.527243,68.4572 230.876117,65.876886 230.876117,62.5822129 L230.876117,58.6650416 C230.876117,55.3688289 228.527243,52.7582367 225.209148,52.7582367 Z M226.210935,62.3641076 C226.210935,63.6691471 225.458819,64.2598276 224.644674,64.2598276 C223.799513,64.2598276 222.202755,63.7317561 221.544201,63.3273632 L221.544201,58.2909268 C222.640584,57.6078723 223.799513,57.110079 224.644674,57.110079 C225.521366,57.110079 226.210935,57.5457765 226.210935,58.9750077 L226.210935,62.3641076 Z M218.17855,48.4438571 C218.676342,48.4438571 219.080054,48.8451709 219.080054,49.3393719 C219.080054,49.8335728 218.676342,50.2348866 218.17855,50.2348866 C217.680241,50.2348866 217.276528,49.8335728 217.276528,49.3393719 C217.276528,48.8451709 217.680241,48.4438571 218.17855,48.4438571 Z M243.560142,66.4767525 C244.837446,65.1224469 245.273207,63.6213692 245.273207,60.5063122 C245.273207,57.477471 244.837446,55.9486811 243.560142,54.5943754 C242.339699,53.2965206 240.596652,52.6032023 238.53415,52.6032023 C236.472166,52.6032023 234.728602,53.2965206 233.507125,54.5943754 C232.258769,55.9214821 231.795094,57.477471 231.795094,60.3621061 C231.795094,63.6213692 232.19984,65.0916555 233.507125,66.4767525 C234.728602,67.7740942 236.472166,68.4658729 238.53415,68.4658729 C240.596652,68.4658729 242.310235,67.7740942 243.560142,66.4767525 Z M238.53415,56.8724212 C240.01512,56.8724212 240.50981,57.738171 240.50981,60.4498614 C240.50981,63.3324438 240.045101,64.1987068 238.53415,64.1987068 C237.024751,64.1987068 236.557457,63.3324438 236.557457,60.4498614 C236.557457,57.738171 237.053181,56.8724212 238.53415,56.8724212 Z M254.746552,68.2925177 L259.393643,68.2925177 L259.393643,58.2273427 C259.393643,54.4784974 257.070097,52.6027917 252.421973,52.6027917 C250.997864,52.6027917 249.922676,52.7762496 249.021688,53.1519039 C248.239074,53.4695679 247.773332,53.7856923 246.814966,54.6806938 L249.778972,57.6222929 C250.649979,56.7857949 251.143635,56.5846249 252.275685,56.5846249 C253.960837,56.5846249 254.629212,57.1316843 254.629212,58.5162681 L254.629212,58.920661 L251.46309,58.920661 C248.179629,58.920661 246.146591,60.6501078 246.146591,63.4772657 C246.146591,66.4768552 248.179629,68.437237 251.31732,68.437237 C252.044623,68.437237 252.683533,68.3217695 253.206137,68.119573 C253.758205,67.8906908 254.07766,67.6587293 254.746552,67.0239146 L254.746552,68.2925177 Z M252.218824,62.0639433 L254.629212,62.0639433 L254.629212,62.4678231 C254.629212,63.9673612 253.960837,64.6001232 252.479868,64.6001232 C251.346784,64.6001232 250.70684,64.140819 250.70684,63.3043211 C250.70684,62.5540388 251.31732,62.0639433 252.218824,62.0639433 Z M285.082129,47.1531869 C283.780013,46.8365493 282.466008,47.6273735 282.146036,48.9195833 L281.226439,52.9445243 C280.757078,52.8316227 280.257218,52.7715796 279.741334,52.7433542 C278.887903,52.6448219 278.138889,52.7582367 277.534612,52.7582367 C274.215484,52.7582367 271.866609,55.3688289 271.866609,58.6650416 L271.866609,62.5822129 C271.866609,65.876886 274.215484,68.4572 277.534612,68.4572 C278.599463,68.4572 279.82094,68.114903 281.199559,67.3697526 L281.199559,68.3330083 L285.83321,68.3330083 L285.83321,55.7131787 L285.828558,55.7106127 C285.829075,54.0401827 286.021885,53.2498717 286.021885,53.2498717 L286.860326,50.0686134 C287.179781,48.7753772 286.384244,47.4703377 285.082129,47.1531869 Z M284.565211,48.4443703 C285.063519,48.4443703 285.467232,48.8451709 285.467232,49.3393719 C285.467232,49.8335728 285.063519,50.2348866 284.565211,50.2348866 C284.067419,50.2348866 283.663706,49.8335728 283.663706,49.3393719 C283.663706,48.8451709 284.067419,48.4443703 284.565211,48.4443703 Z M281.199559,63.3273632 C280.541006,63.7317561 278.944247,64.2598276 278.099087,64.2598276 C277.284424,64.2598276 276.532826,63.6696603 276.532826,62.3641076 L276.532826,58.9755209 C276.532826,57.5457765 277.221877,57.110079 278.099087,57.110079 C278.944247,57.110079 280.103177,57.6078723 281.199559,58.2909268 L281.199559,63.3273632 Z" id="logo_wirenboard"></path></g></g></svg></div>';
        $titlePageHTML.= '        <div class="col-auto" style="text-align: right;">';
        $titlePageHTML.= '          <a href="'.$currentUrl.'">'.$currentUrl.'</a><br>';
        $titlePageHTML.= '          <span>'.date('d-m-Y H:i').'</span>';
        $titlePageHTML.= '        </div>';
        $titlePageHTML.= '    </div>';
        $titlePageHTML.= '    <div class="title-page-content">';
        $titlePageHTML.= '        <h1 style="display: block; margin-top: 0; border: 0; text-align: center; font-size: 40px; font-weight: bold; color: #5cb300">'.$page->getTitle()->getText().'</h1>';
        $titlePageHTML.= '        <div style="margin-top: 5px;">';
        $titlePageHTML.= '            <span style="display: block; text-align: center; font-size: 25px;">Руководство по эксплуатации</span>';
        $titlePageHTML.= '            <span style="display: block; margin-top: 10px; text-align: center; font-size: 15px;">Самая актуальная документация всегда доступна на нашем сайте по ссылке: <a href="'.$currentUrl.'">'.$currentUrl.'</a></span>';
        $titlePageHTML.= '        </div>';
        $titlePageHTML.= '        <div style="margin-top: 15px; text-align: center; font-size: 15px;">Этот документ составлен автоматически из основной страницы документации<br>и ссылок первого уровня.</div>';
        $titlePageHTML.= '    </div>';
        $titlePageHTML.= '</div>';
        $out->addHTML($titlePageHTML);
        $out->addHTML($menuHTML.$titleHTML);
        $out->addHTML($pageText);

        foreach ($pageLinks as $pageLink) {
            $out->addHTML('<h1 id="'.$pageLink->getTitle()->mUrlform.'" class="page-break">'.$this->getPageLinkTitle($pageLink).'</h1>');
            $out->addHTML($this->cleanText($pageLink->getParserOutput()->getText()));
        }

        $out->setPageTitle($page->getTitle()->getText());
    }

    /**
     * @return string
     */
	protected function getGroupName() {
		return 'other';
	}

    /**
     * @param string $table
     * @param array|string[] $columns
     * @param array $condition
     * @return bool|\stdClass|null
     */
	private function getModel(string $table, array $columns = ['*'], array $condition = []) {
        $model = null;

        $res = $this->db->select($table, $columns, $condition, __METHOD__, []);
        if ($this->db->numRows($res) > 0) {
            $model = $this->db->fetchObject($res);
        }

        return $model;
    }

    /**
     * @param array $condition
     * @return bool|\stdClass|null
     */
	private function getPage(array $condition) {
	    $page = $this->getModel('page', [
	        'page_id',
            'page_is_redirect',
        ], $condition);
        if ($page && $page->page_is_redirect) {
            $redirect = $this->getModel('redirect', ['rd_title'], ['rd_from' => $page->page_id]);
            if (!$redirect) {
                return null;
            }
            $page = $this->getPage([
                'page_title' => $redirect->rd_title,
            ]);
        }
        return $page;
    }

    /**
     * @param $text
     * @return array
     */
    private function getPageLinksFromText($text) {
        $links = [];
        preg_match_all('/href="\/wiki\/([^"]*)"/i', $text, $matches);
        if (count($matches) === 2) {
            foreach ($matches[1] as $m) {
                $canAdd = true;
                foreach (['images', ':', '#'] as $s) {
                    if (strpos($m, $s) !== false) {
                        $canAdd = false;
                        continue;
                    }
                }
                if ($canAdd) {
                    $links[] = urldecode($m);
                }
            }
        }
        $links = array_unique($links);

        $pageLinks = [];
        foreach ($links as $link) {
            $page = $this->getPage([
                'page_title' => $link,
            ]);
            if ($page) {
                $pageLink = Article::newFromID($page->page_id);
                if ($pageLink) {
                    $isFound = false;
                    foreach ($pageLinks as $pl) {
                        if ($pl->getPage()->mTitle->mUrlform == $pageLink->getPage()->mTitle->mUrlform) {
                            $isFound = true;
                            break;
                        }
                    }
                    if (!$isFound) {
                        $pageLinks[] = $pageLink;
                    }
                }
            }
        }
        return $pageLinks;
    }

    /**
     * @param $page
     * @return mixed
     */
    private function getPageLinkTitle($page) {
        return (!empty($page->getParserOutput()->mProperties['displaytitle']))
            ? $page->getParserOutput()->mProperties['displaytitle']
            : $page->getPage()->getTitle()->getText();
    }

    /**
     * @param $pageText
     * @return string|string[]|null
     */
    private function cleanText($pageText) {
        $pageText = str_replace('/Служебная:Мой_язык', '', $pageText);
        $pageText = str_replace('/%D0%A1%D0%BB%D1%83%D0%B6%D0%B5%D0%B1%D0%BD%D0%B0%D1%8F:%D0%9C%D0%BE%D0%B9_%D1%8F%D0%B7%D1%8B%D0%BA', '', $pageText);
        $pageText = preg_replace('/<div class="mw-pt-languages noprint".*>.*<\/div>/i', '', $pageText);
        $pageText = preg_replace('/<span class="mw-editsection".*>.*<\/span>/i', '', $pageText);
        $pageText = preg_replace('/<p>&lt;translate&gt;[\r\n]<\/p>/i', '', $pageText);
        $pageText = str_replace('<p>&lt;translate&gt;[\r\n]</p>', '', $pageText);
        $pageText = str_replace('&lt;/translate&gt;', '', $pageText);
        return $pageText;
    }
}
