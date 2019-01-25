<?php

/**
 * Plugin Name:       WP Brewing
 * Plugin URI:        https://frankensteiner.familie-steinberg.org/wp-brewing/
 * Description:       Embed brew recipes and other data from "BeerSmith" or "Kleiner Brauhelfer" into posts and pages.
 * Version:           0.0.1
 * Author:            Frank Steinberg
 * Author URI:        https://frankensteiner.familie-steinberg.org/
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       wp-brewing
 * Domain Path:       /languages
 */



define(RECIPE_TYPE_ALLGRAIN, 1);
define(RECIPE_TYPE_EXTRACT, 2);
define(RECIPE_TYPE_BIAB, 3);

// note: order matters!
define(USAGE_MASH, 1);
define(USAGE_FIRSTWORT, 2);
define(USAGE_SPARGE, 3);
define(USAGE_EXTRACT, 4);
define(USAGE_STEEP, 5);
define(USAGE_BOIL, 6);
define(USAGE_FLAMEOUT, 7);
define(USAGE_WHIRLPOOL, 8);
define(USAGE_PRIMARY, 9);
define(USAGE_SECONDARY, 10);
define(USAGE_KEG, 11);
define(USAGE_BOTTLE, 12);

define(HOP_LEAF, 1);
define(HOP_PELLET, 2);
define(HOP_PLUG, 3);
define(HOP_EXTRACT, 4);

// note: order matters!
define(STATUS_PREPARING, 10);
define(STATUS_BREWDAY, 20);
define(STATUS_BREWDAY_CRUSHING, 21);
define(STATUS_BREWDAY_MASHING, 22);
define(STATUS_BREWDAY_BOILING, 23);
define(STATUS_BREWDAY_COOLING, 24);
define(STATUS_FERMENTATION, 30);
define(STATUS_FERMENTATION_LAG, 31);
define(STATUS_FERMENTATION_GROWTH, 32);
define(STATUS_FERMENTATION_STATIONARY, 33);
define(STATUS_FERMENTATION_SECONDARY, 35);
define(STATUS_BOTTLED, 40);
define(STATUS_CONDITIONING, 50);
define(STATUS_COMPLETE, 60);
define(STATUS_CONSUMING, 65);
define(STATUS_EMPTIED, 70);
define(STATUS_DUMPED, 71);

define(YEAST_ALE, 1);
define(YEAST_LAGER, 2);

define(YEAST_FORM_DRY, 1);
define(YEAST_FORM_LIQUID, 2);

define(FLOC_LOW, 1);
define(FLOC_MEDIUM, 2);
define(FLOC_HIGH, 3);



    function adjuncts_cmp($a, $b) {
        if ($a["usage"] < $b["usage"]) {
            return -1;
        } elseif ($a["usage"] > $b["usage"]) {
            return 1;
        } else {
            if ($a["time"] == $b["time"]) return 0;
            return ($a["time"] < $b["time"]) ? -1 : 1;
        }
    }

    

    function gaben_cmp($a, $b) {
        return $a["Zeit"] < $b["Zeit"];
    }



class WP_Brewing {

    function __construct() {
        add_action('init', array($this, 'init'));
    }

    function init() {
        load_plugin_textdomain('wp-brewing', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        if (! defined('WP_BREWING_URL')) {
            define('WP_BREWING_URL', plugin_dir_url(__FILE__));
        }

        if (! defined('WP_BREWING_PATH')) {
            define('WP_BREWING_PATH', plugin_dir_path(__FILE__));
        }

        if (! defined('WP_BREWING_BASENAME')) {
            define('WP_BREWING_BASENAME', plugin_basename(__FILE__));
        }

        if (is_admin()) {
            require_once(WP_BREWING_PATH . '/includes/admin.php');
        }

        add_shortcode('kbh-recipe', array($this, 'kbh_recipe_shortcode'));
        add_shortcode('kbh-recipes', array($this, 'kbh_recipes_shortcode'));

        add_shortcode('bs-recipe', array($this, 'bs_recipe_shortcode'));
        add_shortcode('bs-recipes', array($this, 'bs_recipes_shortcode'));

        add_shortcode('bjcp-styleguide', array($this, 'bjcp_styleguide_shortcode'));
        add_shortcode('bjcp-style', array($this, 'bjcp_style_shortcode'));

        // if (isset($_GET["attachment_id"] ) && isset($_GET['download_2075'])) {
        if (isset($_GET['download_2075'])) {
            $this->kbh_send_2075($_GET['download_2075']);
        }

    }



    function calcBarAtC($co2, $temp) {
        // formula from from KBH source code
        return $co2 / ((pow(2.71828182845904, (-10.73797 + (2617.25 / ($temp + 273.15))))) * 10) - 1.013;
    }


    
    function calcCo2AtC($bar, $temp) {
        // formula from from KBH source code
        return (1.013 + $bar) * pow(2.71828182845904, (-10.73797 + (2617.25 / ($temp + 273.15)))) * 10;
    }


    
    function calcPsiAtF($co2, $temp) {
        return $this->barToPsi($this->calcBarAtC($co2, $this->fToC($temp)));
    }


    
    function calcAbv($og, $fg) {
        // https://hobbybrauer.de/forum/wiki/doku.php/alkoholgehalt
        // (Stammwürze °P - tatsächlicher Restextrakt °P ) / (2.0665 - 0.010665 * Stammwürze °P)
        if ($og and $fg) {
            // return ($this->sgToPlato($og) - $this->sgToPlato($fg) / (2.0665 - 0.010665 * $this->sgToPlato($og)));
            // return ($og - $fg) * 131.25;
            // http://www.brewunited.com/abv_calculator.php
            return ($this->calcAbw($og, $fg) * ($fg / .794));
        } else {
            return null;
        }
    }



    function calcAbw($og, $fg) {
        if ($og and $fg) {
            return (81.92 * ($this->sgToPlato($og) - $this->sgToPlato($fg)) / (206.65 - 1.0665 * $this->sgToPlato($og)));
        } else {
            return null;
        }
    }



    function calcSudhausausbeuteTraditional($m, $w, $s) {
        // m = Malz (kg), w = Würze vor Hopfenseihen (l) @ 98°C, s = Stammwürze (°P)
        return ( $w * $s * $this->platoToSg($s) * 0.96 ) / $m;
        // Hint: $s * platoToGravity($s)  converts %weight($s) into %vol.
    }

    
    
    function calcKaltwuerzeausbeute($m, $w, $s) {
        // m = Malz (kg), w = Würze nach Hopfenseihen (l) @ 20°C, s = Stammwürze (°P)
        return ( $w * $s * $this->platoToSg($s) ) / $m;
    }

    
    
    function waterGravity($t) {
        $g = (999.83952 +
              $t * 16.952577 +
              pow($t,2) * -0.0079905127 +
              pow($t,3) * -0.000046241757 +
              pow($t,4) * 0.00000010584601 +
              pow($t,5) * -0.00000000028103006) / (1 + $t * 0.016887236);
        return $g;
    }


    
    function calcVolAtTemp($v1, $t1, $t2) {
        $g1 = $this->waterGravity($t1);
        $g2 = $this->waterGravity($t2);

        return ($g1 * $v1) / $g2;
    }



    function abvToAbw($v) {
        // https://alcohol.stackexchange.com/questions/1064/by-volume-by-weight-conversion-formula
        // return 0.1893 * $v * $v + 0.7918 * $v + 0.0002;
        return 0.789 * $v / (1 - 0.211 * $v);
    }


    
    function co2gToVols($v) {
        return $v / 1.96;
    }


    
    function calToJoule($v) {
        return $v * 4.184;

    }



    function ebcToSrm($v) {
        return $v * 0.508;
    }


    
    function srmToEbc($v) {
        return $v / 0.508;
    }


    
    function ebcToLovibond($v) {
        return ($v + 1.497) / 2.669;
    }


    
    function cToF($v) {
        return ($v * 9.0 / 5.0) + 32.0;
    }


    
    function fToC($v) {
        return ($v - 32) * 5 / 9;
    }


    
    function barToPsi($v) {
        return $v * 14.5038;
    }


    
    function dhToPpm($v) {
        return $v * 17.83;
    }
    


    function ppmToDh($v) {
        return $v / 17.83;
    }
    


    function localToUtc($v) {
        # TBD
        return $v . "T00:00:00Z";
    }
    


    function flOzToL($v) {
        return $v * 0.0295735;
    }
    


    function gToOz($v) {
        return $v * 0.035274;
    }
    


    function lToGal($v) {
        return $v * 0.264171999999248;
    }



    function kgToLb($v) {
        return $v * 2.20462;
    }
    


    function platoToDensity($v) {
        // https://de.wikipedia.org/wiki/Stammwürze#Grad_Plato
        return 4.13 * $v + 997;
    }
    


    function platoToSg($v) {
        // https://www.brewersfriend.com/plato-to-sg-conversion-chart/
        return 1.0 + ( $v / ( 258.6 - ( ( $v / 258.2 ) * 227.1 ) ) );
    }
    


    function sgToPlato($v) {
        // https://www.brewersfriend.com/plato-to-sg-conversion-chart/
        return (-1 * 616.868) + (1111.14 * $v) - (630.272 * pow($v,2)) + (135.997 * pow($v,3));
    }
    


    function renderDate($v) {
        return substr($v, 0, 10);
    }



    function renderStyleName($style_id) {
        $styleguide_name = get_option('wp_brewing_bjcp_name', 'styleguide');
        $query = new WP_Query( array(
            'post_type' => 'attachment',
            'name' => $styleguide_name ) );
        $ret = null;
        while ($query->have_posts()) {
            $query->the_post();
            $path = get_attached_file(get_the_ID(), true);
            $doc = new DOMDocument();
            $doc->load($path);
            $xpath = new DOMXPath($doc);
            $q = "//subcategory[@id=" . '"' . $style_id . '"' . "]";
            $styles = $xpath->query($q);
            if ( $styles->length < 1 ) {
                $ret = "(Unbekannte Bierstil-ID " . $style_id . ")";
            }
            foreach ($styles as $style) {
                $ret = $style->getElementsByTagName('name')->item(0)->textContent;
            }
        }
        return $ret;
    }



    function renderStyleList() {
        $styleguide_name = get_option('wp_brewing_bjcp_name', 'styleguide');
        $query = new WP_Query( array(
            'post_type' => 'attachment',
            'name' => $styleguide_name ) );
        $content = '<p>These pages render the contents of the <a href="https://www.bjcp.org/stylecenter.php">BJCP style guideline</a> (2015). The source is taken from its XML representation, available on <a href="https://github.com/meanphil/bjcp-guidelines-2015">GitHub</a>.</p>';
        while ($query->have_posts()) {
            $query->the_post();
            $path = get_attached_file(get_the_ID(), true);
            $doc = new DOMDocument();
            $doc->load($path);
            $xpath = new DOMXPath($doc);
            // $q = "(//category/name | //subcategory/name | //specialty/name)/text()";
            $q = "(//subcategory/name | //specialty/name)/text()";
            $names = $xpath->query($q);
            $list = null;
            foreach ($names as $name) {
                $n = $name->textContent;
                if ($list) { $list .= ", "; }
                $list .= "<a href=\"/bjcp-styleguide?id=" . $n . "\">" . $n . "</a>";
            }
        }
        $content .= $list;

        return $content;
    }



    function childNode($node, $childname) {
        $parent = $node->parentNode;
        foreach ($node->childNodes as $pp) {
            if ($pp->nodeName == $childname) {
                return $pp;
            }
        }
        return null;
    }



    function attr($node, $attrname) {
        if (! $node) { return null; }
        return $node->getAttribute($attrname);
    }


    
    function formatString($str, $data) {
        return preg_replace_callback('#{(\w+?)(\.(\w+?))?}#', function($m) use ($data) {
                return count($m) === 2 ? $data[$m[1]] : $data[$m[1]][$m[3]];
            }, $str);
    }
    


    function renderStyle($id_or_name) {
        $styleguide_name = get_option('wp_brewing_bjcp_name', 'styleguide');
        $query = new WP_Query( array(
            'post_type' => 'attachment',
            'name' => $styleguide_name ) );
        $content = null;
        while ($query->have_posts()) {
            $query->the_post();
            $path = get_attached_file(get_the_ID(), true);
            $doc = new DOMDocument();
            $doc->load($path);
            $xpath = new DOMXPath($doc);
            $q = "//class[@type=" . '"' . $id_or_name . '"' . "] | //category[@id=" . '"' . $id_or_name . '"' . "] | //category[name=" . '"' . $id_or_name . '"' . "] | //subcategory[@id=" . '"' . $id_or_name . '"' . "] | //subcategory[name=" . '"' . $id_or_name . '"' . "] | //specialty[name=" . '"' . $id_or_name . '"' . "]";
            $styles = $xpath->query($q);
            if ( $styles->length < 1 ) {
                $content = "(Unbekannter Bierstil " . $id_or_name . ")";
            }
            foreach ($styles as $style) {

                $class_fragment = "";
                $node = $style;
                $l0 = "/glossary/bjcp-";
                if ($node->nodeName == "specialty") {
                    $l = strtolower(str_replace(" ", "-", $this->childNode($node, "name")->textContent));
                    $class_fragment = "<br/> > > > <a href=\"" . $l0 . $l . "\">Specialty \"" . $this->childNode($node, "name")->textContent . "\"</a>";
                    $node = $node->parentNode;
                }
                if ($node->nodeName == "subcategory") {
                    $l = strtolower($this->attr($node, "id"));
                    $class_fragment = "<br/> > > <a href=\"" . $l0 . $l . "\">Subcategory " . $this->attr($node, "id") . " (" . $this->childNode($node, "name")->textContent . ")</a>" . $class_fragment;
                    $node = $node->parentNode;
                }
                if ($node->nodeName == "category") {
                    $l = strtolower($this->attr($node, "id"));
                    $class_fragment = "<br/> > <a href=\"" . $l0 . $l . "\">Category " . $this->attr($node, "id") . " (" . $this->childNode($node, "name")->textContent . ")</a>" . $class_fragment;
                    $node = $node->parentNode;
                }
                if ($node->nodeName == "class") {
                    $l = $this->attr($node, "type");
                    $class_fragment = "<a href=\"" . $l0 . $l . "\">Class " . $this->attr($node, "type") . "</a>" . $class_fragment;
                    $node = $node->parentNode;
                }
                $class_fragment = "<dt>Classification</dt><dd>" . $class_fragment . "</dd>";

                $stats_fragment = "";
                $ibu = "unbestimmt";
                $og = "unbestimmt";
                $fg = "unbestimmt";
                $srm = "unbestimmt";
                $abv = "unbestimmt";
                $stats = $this->childNode($style, 'stats');
                $value = $this->childNode($this->childNode($stats, 'ibu'), 'low')->textContent / $this->sgToPlato($this->childNode($this->childNode($stats, 'og'), 'high')->textContent);
                if ($value < 1.5) { $descr = "sehr malzig"; }
                elseif ($value < 2.0) { $descr = "malzig"; }
                elseif ($value < 2.2) { $descr = "ausgewogen"; }
                elseif ($value < 3.0) { $descr = "herb"; }
                elseif ($value < 6.0) { $descr = "sehr herb"; }
                else { $descr = "Hopfenbombe"; }
                $value = $this->childNode($this->childNode($stats, 'ibu'), 'high')->textContent / $this->sgToPlato($this->childNode($this->childNode($stats, 'og'), 'low')->textContent);
                if ($value < 1.5) { $descr .= " - sehr malzig"; }
                elseif ($value < 2.0) { $descr .= " - malzig"; }
                elseif ($value < 2.2) { $descr .= " - ausgewogen"; }
                elseif ($value < 3.0) { $descr .= " - herb"; }
                elseif ($value < 6.0) { $descr .= " - sehr herb"; }
                else { $descr .= " - Hopfenbombe"; }
                if ($this->attr($this->childNode($stats, 'og'), 'flexible') == "false") {
                    $low = $this->childNode($this->childNode($stats, 'og'), 'low')->textContent;
                    $high = $this->childNode($this->childNode($stats, 'og'), 'high')->textContent;
                    $og = number_format($this->sgToPlato($low), 1, ",", ".") . " - " . number_format($this->sgToPlato($high), 1, ",", ".") . " °P</td>" . "<td>OG " . number_format($low, 3, ",", ".") . " - " . number_format($high, 3, ",", ".");
                    $stats_fragment .= $this->formatString("<tr><td>Stammwürze</td><td>{og}</td></tr>", [ "og" => $og ]);
                }
                if ($this->attr($this->childNode($stats, 'fg'), 'flexible') == "false") {
                    $low = $this->childNode($this->childNode($stats, 'fg'), 'low')->textContent;
                    $high = $this->childNode($this->childNode($stats, 'fg'), 'high')->textContent;
                    $fg = number_format($this->sgToPlato($low), 1, ",", ".") . " - " . number_format($this->sgToPlato($high), 1, ",", ".") . " GG%</td>" . "<td>FG " . number_format($low, 3, ",", ".") . " - " . number_format($high, 3, ",", ".");
                    $stats_fragment .= $this->formatString("<tr><td>Restextrakt</td><td>{fg}</td></tr>", [ "fg" => $fg ]);
                }
                if ($this->attr($this->childNode($stats, 'abv'), 'flexible') == "false") {
                    $low = $this->childNode($this->childNode($stats, 'abv'), 'low')->textContent;
                    $high = $this->childNode($this->childNode($stats, 'abv'), 'high')->textContent;
                    $abv = number_format($low, 1, ",", ".") . " - " . number_format($high, 1, ",", ".") . " %vol" . "</td>" . "<td>";
                    $stats_fragment .= $this->formatString("<tr><td>Alkohol</td><td>{abv}</td></tr>", [ "abv" => $abv ]);
                }
                if ($this->attr($this->childNode($stats, 'ibu'), 'flexible') == "false") {
                    $low = $this->childNode($this->childNode($stats, 'ibu'), 'low')->textContent;
                    $high = $this->childNode($this->childNode($stats, 'ibu'), 'high')->textContent;
                    $ibu = $low . " - " . $high . " IBU" . "</td>" . "<td>" . "(" . $descr . ")";
                    $stats_fragment .= $this->formatString("<tr><td>Bittere</td><td>{ibu}</td></tr>", [ "ibu" => $ibu ]);
                }
                if ($this->attr($this->childNode($stats, 'srm'), 'flexible') == "false") {
                    $low = $this->childNode($this->childNode($stats, 'srm'), 'low')->textContent;
                    $high = $this->childNode($this->childNode($stats, 'srm'), 'high')->textContent;
                    $srm = number_format($this->srmToEbc($low), 1, ",", ".") . " - " . number_format($this->srmToEbc($high), 1, ",", ".") . " EBC</td>" . "<td>" . number_format($low, 1, ",", ".") . " - " . number_format($high, 1, ",", ".") . " SRM";
                    $stats_fragment .= "";
                    $stats_fragment .= $this->formatString("<tr><td>Farbe</td><td>{srm}</td></tr>", [ "srm" => $srm ]);
                }
                if ($stats_fragment != "") {
                    $stats_fragment = "<table>" . $stats_fragment . "</table>";
                }

                $details_fragment = "";
                $details = array('Notes', 'Aroma', 'Appearance', 'Flavor', 'Mouthfeel', 'Impression', 'Comments', 'History', 'Ingredients', 'Comparison', 'Examples', 'Tags');
                foreach ($details as $detail) {
                    $value = $this->childNode($style, strtolower($detail))->textContent;
                    if ($value) {
                        $details_fragment .= "<dt>" . $detail . "</dt><dd>" . $value . "</dd>";
                    }
                }
                
                $specialties_fragment = "";
                if ($this->childNode($style, 'entry_instructions')) {
                    $specialties_fragment = $this->formatString(
                        '<dt>Specialty entry instructions</dt><dd>{instr}</dd>',
                        [
                            'instr' => $doc->saveXML($this->childNode($style, 'entry_instructions'))
                        ]);
                    /*
                    $list = null;
                    $specialties = $style->getElementsByTagName('specialty');
                    foreach ($specialties as $specialty) {
                        if ($list) { $list .= ", "; }
                        $n = $specialty->getElementsByTagName('name')->item(0)->textContent;
                        // $list .= $specialty->getElementsByTagName('name')->textContent;
                        $list .= $n;
                    }
                    $specialties_fragment .= "<dt>Defined Specialties</dt><dd>" . $list . "</dd>";
                    */
                    $details_fragment .= $specialties_fragment;
                }

                $children_fragment = "";
                if ($style->nodeName == "subcategory") {
                    $list = null;
                    foreach ($style->childNodes as $c) {
                        if ($c->nodeType == XML_ELEMENT_NODE) {
                            if ($list) { $list .= ", "; }
                            $n = $this->childNode($c, 'name')->textContent;
                            $list .= $n;
                        }
                    }
                    if ($list) {
                        $children_fragment = "<dt>Specialties</dt>" . "<dd>" . $list . "</dd>";
                    }
                }
                if ($style->nodeName == "category") {
                    $list = null;
                    foreach ($style->childNodes as $c) {
                        if ($c->nodeType == XML_ELEMENT_NODE) {
                            if ($list) { $list .= ", "; }
                            $n = $this->childNode($c, 'name')->textContent;
                            $list .= $n;
                        }
                    }
                    if ($list) {
                        $children_fragment = "<dt>Subcategories</dt>" . "<dd>" . $list . "</dd>";
                    }
                }
                if ($style->nodeName == "class") {
                    $list = null;
                    foreach ($style->childNodes as $c) {
                        if ($c->nodeType == XML_ELEMENT_NODE) {
                            if ($list) { $list .= ", "; }
                            $n = $this->childNode($c, 'name')->textContent;
                            $list .= $n;
                        }
                    }
                    if ($list) {
                        $children_fragment = "<dt>Categories</dt>" . "<dd>" . $list . "</dd>";
                    }
                }

                $details_fragment = "<dl>" . $class_fragment . $details_fragment . $children_fragment . "</dl>";
                    
                $content .= $this->formatString(
                    '<p style="font-size: 70%;">Die folgenden Informationen entstammen einer XML-Form der BJCP Style Guidelines, die per <a href="https://github.com/meanphil/bjcp-guidelines-2015">GitHub</a> öffentlich verfügbar ist. Ich bereite diese Daten hier lediglich für meine persönlichen und nicht gewerblichen Zwecke auf, um sie leichter lesbar zu machen und Werte mit den in Deutschland gebräuchlicheren Einheiten darzustellen.</p>
                     <h2>{name}</h2>
                     {stats_fragment}
                     {details_fragment}
                     ',
                    [
                        'id' => ($style->localName == "specialty") ? ("Specialty") : $style->getAttribute('id'),
                        'name' => $this->childNode($style, 'name')->textContent,
                        'stats_fragment' => $stats_fragment,
                        'details_fragment' => $details_fragment
                    ]);
            }
        }
        return $content;
    }



    function statusWord($status) {
        switch($status) {
        case STATUS_PREPARING: $value = "in Vorbereitung"; break;
        case STATUS_BREWDAY: $value = "Brautag"; break;
        case STATUS_BREWDAY_CRUSHING: $value = "Schroten"; break;
        case STATUS_BREWDAY_MASHING: $value = "Maischen"; break;
        case STATUS_BREWDAY_BOILING: $value = "Würzekochen"; break;
        case STATUS_BREWDAY_COOLING: $value = "Hopfenseihen"; break;
        case STATUS_FERMENTATION: $value = "in Gärung"; break;
        case STATUS_FERMENTATION_LAG: $value = "Gärung (Lag Phase)"; break;
        case STATUS_FERMENTATION_GROWTH: $value = "Gärung (Vermehrung)"; break;
        case STATUS_FERMENTATION_STATIONARY: $value = "Gärung (stationär)"; break;
        case STATUS_FERMENTATION_SECONDARY: $value = "Nachgärung"; break;
        case STATUS_BOTTLED: $value = "abgefüllt"; break;
        case STATUS_CONDITIONING: $value = "in Reifung"; break;
        case STATUS_COMPLETE: $value = "trinkfertig"; break;
        case STATUS_CONSUMING: $value = "wird kunsumiert"; break;
        case STATUS_EMPTIED: $value = "leer"; break;
        case STATUS_DUMPED: $value = "entsorgt"; break;
        default: $value = "undefiniert";
        }
        return $value;
    }

    

    function renderRecipe($recipe) {
        
        $content .= $this->formatString(
            '<table class="wp-brewing-recipe">
               <style type="text/css">

                 table.wp-brewing-recipe { font-size:11pt; }

                 table.wp-brewing-recipe tr th { background:white; color:#333 }

                 /* fix glossary link color on white heading line */
                 table.wp-brewing-recipe tr th a.glossaryLink { color:#333 }
                 table.wp-brewing-recipe tr th a.glossaryLink:hover { color:#333 }

                 /* table.wp-brewing-recipe tr td { border:1px solid red } */
                 table.wp-brewing-recipe tr th { padding-left:4px; width:70% }
                 table.wp-brewing-recipe tr th+th { text-align:right }
                 table.wp-brewing-recipe tr th+th[colspan] { width:30%; text-align:right; padding-right:4px }
                 table.wp-brewing-recipe tr th+th+th { padding-right:4px; width:18%; text-align:right }
                 table.wp-brewing-recipe tr th[colspan] { text-align:center }
                 table.wp-brewing-recipe tr th+th          { width:12% }



                 table.wp-brewing-recipe tr td[colspan] { text-align:center }
                 table.wp-brewing-recipe tr td+td { text-align:right }

                 table.wp-brewing-recipe tr td+td[colspan] { width:30% }
                 table.wp-brewing-recipe tr td+td          { width:12% }
                 table.wp-brewing-recipe tr td+td+td       { width:18% }

                 table.wp-brewing-recipe td span[data-cmtooltip] { color: #d0ffd0 }

                 @media only screen and (max-width: 800px) {
                   table.wp-brewing-recipe { font-size:9pt }
                   table.wp-brewing-recipe tr th+th          { width:16% }
                   table.wp-brewing-recipe tr td+td          { width:16% }
                 }

               </style>
               <tr>
                 <th>{name}</th>
                 <th colspan="2">{brew_date}</th>
               </tr>',
            [
                'name' => $recipe["name"],
                'brew_date' => $recipe["brew_date"] ? $this->renderDate($recipe["brew_date"]) : "noch nicht gebraut"
            ]);

        if (strlen($recipe["description"]) > 0) {
            $content .= $this->formatString(
                '<tr>
                   <td colspan="3">{value}</td>
                 </tr>',
                [
                    'value' => $recipe["description"]
                ]);
        }
        
        if ($recipe["status"] > 0) {
            $value = $this->statusWord($recipe["status"]);
            $content .= $this->formatString(
                '<tr>
                   <td>Status</td>
                   <td colspan="2">{value}</td>
                 </tr>',
                [
                    'value' => $value
                ]);
        }

        if ($recipe["bjcp2015_style_id"]) {
            $content .= $this->formatString(
                '<tr>
                   <td>Stil gemäß BJCP Guidelines (2015)</td>
                   <!--<td colspan="2"><span data-cmtooltip=\'{tt}\'><a href="/bjcp-styleguide?id={style_id}">{style_id}</a></span></td>-->
                   <!--<td colspan="2"><span data-cmtooltip=\'{style_id}\'><a href="/bjcp-styleguide?id={style_id}">{name}</a></span></td>-->
                   <td colspan="2">{name}</td> <!-- finally we rely on the auto-generated glossary posts -->
                 </tr>',
                [
                    'style_id' => $recipe["bjcp2015_style_id"],
                    'name' => $this->renderStyleName($recipe["bjcp2015_style_id"]),
                    'tt' => $this->renderStyleName($recipe["bjcp2015_style_id"])
                ]);
        }

        if (($recipe["planned_batch_volume"] > 0) || ($recipe["bottled_volume"] > 0)) {
            $tt = null;
            if ($recipe["planned_batch_volume"] > 0) {
                $value = number_format($recipe["planned_batch_volume"], 1, ",", ".");
                $label = "Geplante Ausschlagmenge";
                $tt = $label . ": " . $value . " Liter (" . number_format($this->lToGal($recipe["planned_batch_volume"]), 2, ",", ".") . " gal)" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["bottled_volume"] > 0) {
                $value = number_format($recipe["bottled_volume"], 1, ",", ".");
                $label = "Abgefüllte Biermenge";
                $tt = $label . ": " . $value . " Liter (" . number_format($this->lToGal($recipe["bottled_volume"]), 2, ",", ".") . " gal)" . ($tt ? (",<br/>" . $tt) : "");
            }
            $content .= $this->formatString(
                '<tr>
                   <td>{label}</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{value} Liter</span></td>
                 </tr>',
                [
                    'label' => $label,
                    'value' => $value,
                    'tt' => $tt
                ]);
        }

        if ($recipe["containers"]) {
            $content .= $this->formatString(
                '<tr>
                  <td>Gebinde</td>
                  <td colspan="2">{containers}</td>
                </tr>',
                [
                    'containers' => $recipe["containers"]
                ]);
        }

        if ($recipe["pack_color"]) {
            $content .= $this->formatString(
                '<tr>
                  <td>Kronkorkenfarbe</td>
                  <td colspan="2">{pack_color}</td>
                </tr>',
                [
                    'pack_color' => $recipe["pack_color"]
                ]);
        }

        if ($recipe["stock"]) {
            $content .= $this->formatString(
                '<tr>
                   <td>Restbestand</td>
                   <td colspan="2">{stock}</td>
                 </tr>',
                [
                    'stock' => ($recipe["stock"]) ? $recipe["stock"] : "0"
                ]);
        }

        if ($recipe["og"] or $recipe["planned_og"]) {
            $tt = null;
            if ($recipe["planned_og"]) {
                $value = number_format($this->sgToPlato($recipe["planned_og"]), 1, ",", ".");
                $label = "Geplante Stammwürze";
                $tt = $label . ": " . $value . " °P (OG " . number_format($recipe["planned_og"], 3, ",", ".") . ")" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["og"]) {
                $value = number_format($this->sgToPlato($recipe["og"]), 1, ",", ".");
                $label = "Erzielte Stammwürze";
                $tt = $label . ": " . $value . " °P (OG " . number_format($recipe["og"], 3, ",", ".") . ")" . ($tt ? (",<br/>" . $tt) : "");
            }
            $content .= $this->formatString(
                '<tr>
                   <td>{label}</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{value} °P</span></td>
                 </tr>',
                [
                    'label' => $label,
                    'value' => $value,
                    'tt' => $tt
                ]);
        }
        
        if ($recipe["fg"] or $recipe["estimated_fg"] or $recipe["current_g"]) {
            $tt = null;
            if ($recipe["current_g"]) {
                $value = number_format($this->sgToPlato($recipe["current_g"]), 1, ",", ".");
                $label = "Bisheriger Restextrakt";
                $tt = $label . ": " . $value . " GG% (SG " . number_format($recipe["current_g"], 3, ",", ".") . ")" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["estimated_fg"]) {
                $value = number_format($this->sgToPlato($recipe["estimated_fg"]), 1, ",", ".");
                $label = "Erwarteter Restextrakt";
                $tt = $label . ": " . $value . " GG% (FG " . number_format($recipe["estimated_fg"], 3, ",", ".") . ")" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["fg"]) {
                $value = number_format($this->sgToPlato($recipe["fg"]), 1, ",", ".");
                $label = "Gemessener Restextrakt";
                $tt = $label . ": " . $value . " GG% (FG " . number_format($recipe["fg"], 3, ",", ".") . ")" . ($tt ? (",<br/>" . $tt) : "");
            }
            $content .= $this->formatString(
                '<tr>
                   <td>{label}</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{value} GG%</span></td>
                 </tr>',
                [
                    'label' => $label,
                    'value' => $value,
                    'tt' => $tt
                ]);
        }
        
        if ($recipe["abv"] or (($recipe["og"] or $recipe["planned_og"]) and ($recipe["fg"] or $recipe["current_g"] or $recipe["estimated_fg"]))) {
            $tt = null;
            if ($this->calcAbv($recipe["og"], $recipe["current_g"])) {
                $value = number_format($this->calcAbv($recipe["og"], $recipe["current_g"]), 1, ",", ".");
                $label = "Bisheriger Alkohol";
                $tt = $label . ": " . $value . " %vol, " . number_format($this->calcAbw($recipe["og"], $recipe["current_g"]), 1, ",", ".") . " %gew" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($this->calcAbv($recipe["planned_og"], $recipe["estimated_fg"])) {
                $value = number_format($this->calcAbv($recipe["planned_og"], $recipe["estimated_fg"]), 1, ",", ".");
                $label = "Erwarteter Alkohol";
                $tt = $label . ": " . $value . " %vol, " . number_format($this->calcAbw($recipe["planned_og"], $recipe["estimated_fg"]), 1, ",", ".") . " %gew" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($this->calcAbv($recipe["og"], $recipe["fg"])) {
                $value = number_format($this->calcAbv($recipe["og"], $recipe["fg"]), 1, ",", ".");
                $label = "Berechneter Alkohol ohne Nachgärung" . $t;
                $tt = $label . ": " . $value . " %vol, " . number_format($this->calcAbw($recipe["og"], $recipe["fg"]), 1, ",", ".") . " %gew" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["abv"]) {
                $value = number_format($recipe["abv"], 1, ",", ".");
                $label = "Alkohol";
                $tt = "Von Brausoftware übermittelter " . $label . ": " . $value . " %vol" . ($tt ? (",<br/>" . $tt) : "");
            }
            $content .= $this->formatString(
                '<tr>
                   <td>{label}</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{value} %vol</span></td>
                 </tr>',
                [
                    'label' => $label,
                    'value' => $value,
                    'tt' => $tt
                ]);
        }
        
        if ($recipe["ibu"]) {
            $tt = "";
            if (($recipe["og"] || $recipe["planned_og"])) {
                $value = ($recipe["ibu"] / $this->sgToPlato(($recipe["og"] ? $recipe["og"] : $recipe["planned_og"])) );
                if ($value < 1.5) { $descr = "sehr malzig"; }
                elseif ($value < 2.0) { $descr = "malzig"; }
                elseif ($value < 2.2) { $descr = "ausgewogen"; } // "mild bis ausgewogen"
                elseif ($value < 3.0) { $descr = "herb"; }
                elseif ($value < 6.0) { $descr = "sehr herb"; }
                else { $descr = "Hopfenbombe"; }
                
                $tt = "IBU: " . number_format($recipe["ibu"], 0, ",", ".") . "<br/>Bittereindruck: " . number_format($value, 1, ",", ".") . " - " . $descr;
            }
            $content .= $this->formatString(
                '<tr>
                   <td>Berechnete Bittere</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{ibu} IBU</span></td>
                 </tr>',
                [
                    'ibu' => number_format($recipe["ibu"], 0, ",", "."),
                    'tt' => $tt
                ]);
        }
        
        if ($recipe["ebc"]) {
            $content .= $this->formatString(
                '<tr>
                   <td>Berechnete Farbe</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{ebc} EBC</span></td>
                 </tr>',
                [
                    'ebc' => number_format($recipe["ebc"], 0, ",", "."),
                    'tt' => number_format($this->ebcToSrm($recipe["ebc"]), 1, ",", ".") . " SRM, " . number_format($this->ebcToLovibond($recipe["ebc"]), 2, ",", ".") . " °L"
                ]);
        }
        
        if ($recipe["calories"]) {
            $content .= $this->formatString(
                '<tr>
                   <td>{prefix}Energiegehalt</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{kcal} kcal/100ml</span></td>
                 </tr>',
                [
                    'prefix' => $recipe["status"] < STATUS_BOTTLED ? "Voraussichtlicher " : "",
                    'kcal' => number_format($recipe["calories"], 0, ",", "."),
                    'tt' => number_format($this->calToJoule($recipe["calories"]), 0, ",", ".") . " kJ/100ml"
                ]);
        }

        if ($recipe["drink_temp"]) {
            $content .= $this->formatString(
                '<tr>
                   <td>Trinktemperatur</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{temp} °C</span></td>
                 </tr>',
                [
                    'temp' => number_format($recipe["drink_temp"], 0, ",", "."),
                    'tt' => number_format($this->cToF($recipe["drink_temp"]), 0, ",", ".") . " °F"
                ]);
        }

        if ($recipe["song"]) {
            $content .= $this->formatString(
                '<tr>
                   <td>Song zum Bier</td>
                   <td colspan="2">{song}</td>
                 </tr>',
                [
                    'song' => $recipe["song"]
                ]);
        }

        // mash (fermentables, hops, adjuncts)
        if ($recipe["fermentables"]) {
            $grainsum = 0;
            foreach ($recipe["fermentables"] as $f) {
                $grainsum = $grainsum + $f["amount"];
            }
            $content .= $this->formatString(
                '<tr>
                   <th>Schüttung / Maischen</th>
                   <th colspan="2"><span data-cmtooltip="{tt}">{grainsum} kg</span></th>
                 </tr>',
                [
                    'grainsum' => number_format($grainsum, 3, ",", "."),
                    'tt' => number_format($this->kgToLb($grainsum), 3, ".", ",") . " lb"
                ]);
            foreach ($recipe["fermentables"] as $f) {
                if ($f["usage"] != USAGE_MASH) continue;
                $rendered_name = $f["name"];
                if (strlen($f["url"]) >= 1) {
                    $rendered_name = '<a href="' . $f["url"] . '">' . $rendered_name . '</a>';
                }
                $percent = ($f["amount"] / $grainsum) * 100;
    			$content .= $this->formatString(
                    '<tr>
                       <td>{rendered_name}</td>
                       <td>{percent} %</td>
                       <td style="text-align:right"><span data-cmtooltip="{tt_amount}">{amount} kg</span></td>
                     </tr>',
                    [
                        'rendered_name' => $rendered_name,
                        'percent' => number_format($percent, 1, ",", "."),
                        'amount' => number_format($f["amount"], 3, ",", "."),
                        'tt_amount' => number_format($this->kgToLb($f["amount"]), 3, ",", ".") . " lb"
                    ]);
            }
            foreach ($recipe["adjuncts"] as $a) {
                if ($a["usage"] != USAGE_MASH) continue;
                $rendered_name = $a["name"];
                if (strlen($a["url"]) >= 1) {
                    $rendered_name = '<a href="' . $a["url"] . '">' . $rendered_name . '</a>';
                }
                $unit = $a["unit"];
                if ($unit == "kg") {
                    $dose = number_format($a["amount"] / $recipe["planned_batch_volume"] * 1000, 0, ",", ".") . " " . "g" . "/l";
                } else {
                    $dose = number_format($a["amount"] / $recipe["planned_batch_volume"], 3, ",", ".") . " " . $unit . "/l";
                }
    			$content .= $this->formatString(
                    '<tr>
                       <td>{rendered_name}</td>
                       <td>{dose}</td>
                       <td style="text-align:right"><span data-cmtooltip="{tt_amount}">{amount} {unit}</span></td>
                     </tr>',
                    [
                        'rendered_name' => $rendered_name,
                        'dose' => $dose,
                        'amount' => number_format($a["amount"], ($unit == "g" ? 0 : 3), ",", "."),
                        'unit' => $unit,
                        'tt_amount' => ($unit == "g") ? number_format($this->gToOz($a["amount"]), 2, ",", ".") . " oz" : ""
                    ]);
            }
            // TBD: hops during mash? not really required. :-)
        }

        if ($recipe["planned_residual_alkalinity"]) {
            $content .= $this->formatString(
                '<tr>
                   <td>geplante Brauwasser-Restalkalität</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{value} °dH</span></td>
                 </tr>',
                [
                    'value' => number_format($this->ppmToDh($recipe["planned_residual_alkalinity"]), 1, ",", "."),
                    'tt' => number_format($recipe["planned_residual_alkalinity"], 1, ",", ".") . " ppm"
                ]);
        }

        if ($recipe["mash_water_volume"]) {
            $content .= $this->formatString(
                '<tr>
                   <td>Hauptguss &amp; Einmaischen</td>
                   <td><span data-cmtooltip="{tt_temp}">{temp} °C</span></td>
                   <td><span data-cmtooltip="{tt_vol}">{vol} Liter</span></td>
                 </tr>',
                [
                    'temp' => number_format($recipe["mash_in_temp"], 0, ",", "."),
                    'tt_temp' => number_format($this->cToF($recipe["mash_in_temp"]), 0, ",", ".") . " °F",
                    'vol' => number_format($recipe["mash_water_volume"], 1, ",", "."),
                    'tt_vol' => number_format($this->lToGal($recipe["mash_water_volume"]), 2, ",", ".") . " gal"
                ]);
        }

        $sparge_water_temp = 78; // default
        if ($recipe["mash_steps"]) {
            foreach ($recipe["mash_steps"] as $m) {
    			$content .= $this->formatString(
                    '<tr>
                       <td>{name}</td>
                       <td><span data-cmtooltip="{tt}">{temp} °C</span></td>
                       <td>{time} Minuten</td>
                     </tr>',
                    [
                        'name' => $m["name"],
                        'temp' => number_format($m["temp"], 0, ",", "."),
                        'time' => number_format($m["time"], 0, ",", "."),
                        'tt' => number_format($this->cToF($m["temp"]), 0, ",", ".") . " °F"
                    ]);
                $sparge_water_temp = $m["temp"];
            }
        }

        if ($recipe["sparge_water_volume"]) {
            $content .= $this->formatString(
                '<tr>
                   <td>Nachguss</td>
                   <td><span data-cmtooltip="{tt_temp}">{temp} °C</span></td>
                   <td><span data-cmtooltip="{tt_vol}">{vol} Liter</span></td>
                 </tr>',
                [
                    'temp' => number_format($sparge_water_temp, 0, ",", "."),
                    'tt_temp' => number_format($this->cToF($sparge_water_temp), 0, ",", ".") . " °F",
                    'vol' => number_format($recipe["sparge_water_volume"], 1, ",", "."),
                    'tt_vol' => number_format($this->lToGal($recipe["sparge_water_volume"]), 2, ",", ".") . " gal"
                ]);
        }

        if ($recipe["boil_time"] > 0) {
    		$content .= $this->formatString(
                '<tr>
                   <th>Würzekochen</th>
                   <th colspan="2">{time} Minuten</th>
                 </tr>',
                [
                    'time' => number_format($recipe["boil_time"], 0, ",", ".")
                ]);
            foreach ($recipe["hops"] as $h) {
                if (($h["usage"] < USAGE_FIRSTWORT) || ($h["usage"] > USAGE_WHIRLPOOL)) continue;
                $rendered_name = $h["name"];
                if (strlen($h["url"]) >= 1) {
                    $rendered_name = '<a href="' . $h["url"] . '">' . $rendered_name . '</a>';
                }
                $rendered_type = "";
                switch ($h["type"]) {
                case HOP_LEAF: $rendered_type = ", Dolden"; break;
                case HOP_PLUG: $rendered_type = ", Plugs"; break;
                case HOP_PELLET: $rendered_type = ", Pellets"; break;
                case HOP_EXTRACT: $rendered_type = ", Extrakt"; break;
                default: $rendered_type = ", (?)";
                }
                $rendered_alpha = "";
                if ($h["alpha"] > 0) {
                    $rendered_alpha = ", " . number_format($h["alpha"], 1, ",", ".") . " %α";
                }
                $rendered_time = "";
                switch ($h["usage"]) {
                case USAGE_MASH: $rendered_time = "Maische"; break;
                case USAGE_FIRSTWORT: $rendered_time = "Vorderwürze"; break;
                case USAGE_BOIL: $rendered_time = ($h["time"] == 0) ? " Kochende" : (number_format($h["time"], 0, ",", ".") . " Minuten"); break;
                case USAGE_FLAMEOUT: $rendered_time = "Kochende"; break;
                case USAGE_WHIRLPOOL: $rendered_time = "Whirlpool"; break;
                default: $rendered_time = "(?)";
                }
                $amount = number_format($h["amount"], 0, ",", ".");
                $tt_amount = number_format($this->gToOz($h["amount"]), 2, ",", ".") . " oz";
                $dose = number_format($h["amount"] / $recipe["planned_batch_volume"], 1, ",", ".") . " g/l";
                $tt_dose = number_format($this->gToOz($h["amount"]) / $this->lToGal($recipe["planned_batch_volume"]), 2, ",", ".") . " oz/gal";
    			$content .= $this->formatString(
                    '<tr>
                       <td>{rendered_name}{rendered_type}{rendered_alpha}, <span data-cmtooltip="{tt_dose}">{dose}</span></td>
                       <td><span data-cmtooltip="{tt_amount}">{amount} g</span></td>
                       <td>{rendered_time}</td>
                     </tr>',
                    [
                        'rendered_name' => $rendered_name,
                        'rendered_type' => $rendered_type,
                        'rendered_alpha' => $rendered_alpha,
                        'amount' => $amount,
                        'tt_amount' => $tt_amount,
                        'rendered_time' => $rendered_time,
                        'dose' => $dose,
                        'tt_dose' => $tt_dose
                    ]);
    		}
            foreach ($recipe["adjuncts"] as $a) {
                if (($a["usage"] < USAGE_FIRSTWORT) || ($a["usage"] > USAGE_WHIRLPOOL)) continue;
                $rendered_name = $a["name"];
                if (strlen($a["url"]) >= 1) {
                    $rendered_name = '<a href="' . $a["url"] . '">' . $rendered_name . '</a>';
                }
                $rendered_time = "";
                switch ($a["usage"]) {
                case USAGE_MASH: $rendered_time = "Maische"; break;
                case USAGE_FIRSTWORT: $rendered_time = "Vorderwürze"; break;
                case USAGE_BOIL: $rendered_time = (($a["time"] == 0) ? "Kochende" : (number_format($a["time"], 0, ",", ".") . " Minuten")); break;
                case USAGE_FLAMEOUT: $rendered_time = "Kochende"; break;
                case USAGE_WHIRLPOOL: $rendered_time = "Whirlpool"; break;
                default: $rendered_time = "(?)";
                }
                $unit = $a["unit"];
                $amount = number_format($a["amount"], 0, ",", ".");
                $dose = number_format($a["amount"] / $recipe["planned_batch_volume"], 1, ",", ".") . " " . $unit . "/l";
    			$content .= $this->formatString(
                    '<tr>
                       <td>{rendered_name}, {dose}</td>
                       <td>{amount} {unit}</td>
                       <td>{rendered_time}</td>
                     </tr>',
                    [
                        'rendered_name' => $rendered_name,
                        'dose' => $dose,
                        'amount' => number_format($a["amount"], ($unit == "g" ? 0 : 3), ",", "."),
                        'unit' => $unit,
                        'rendered_time' => $rendered_time
                    ]);
            }
        }

        if ($recipe["sudhausausbeute"] or $recipe["estimated_sudhausausbeute"]) {
            $tt = null;
            if ($recipe["estimated_sudhausausbeute"]) {
                $value = number_format($recipe["estimated_sudhausausbeute"], 0, ",", ".");
                $label = "Von Brausoftware erwartete Sudhausausbeute";
                $tt = $label . ": " . $value . " %" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["sudhausausbeute"]) {
                $value = number_format($recipe["sudhausausbeute"], 0, ",", ".");
                $label = "Von Brausoftware berechnete Sudhausausbeute";
                $tt = $label . ": " . $value . " %" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["og"] and $recipe["post_boil_hot_volume"]) {
                $value = number_format($this->calcSudhausausbeuteTraditional($grainsum, $recipe["batch_volume"], $this->sgToPlato($recipe["og"])), 0, ",", ".");
                $label = "Berechnete traditionelle Sudhausausbeute";
                $tt = $label . ": " . $value . " %" . ($tt ? (",<br/>" . $tt) : "");
            } elseif ($recipe["og"] and $recipe["post_boil_roomtemp_volume"] > 0) {
                $value = number_format($this->calcSudhausausbeuteTraditional($grainsum, $this->calcVolAtTemp($recipe["post_boil_roomtemp_volume"], 20, 99), $this->sgToPlato($recipe["og"])), 0, ",", ".");
                $label = "Berechnete traditionelle Sudhausausbeute";
                $tt = $label . ": " . $value . " %" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["og"] && $recipe["batch_volume"]) {
                $value = number_format($this->calcKaltwuerzeausbeute($grainsum, $recipe["batch_volume"], $this->sgToPlato($recipe["og"])), 0, ",", ".");
                $label = "Berechnete Gesamtausbeute";
                $tt = $label . ": " . $value . " %" . ($tt ? (",<br/>" . $tt) : "");
            }
            $content .= $this->formatString(
                '<tr>
                   <td>{label}</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{value} %</span></td>
                 </tr>',
                [
                    'label' => $label,
                    'value' => $value,
                    'tt' => $tt
                ]);
        }

        if ($recipe["post_boil_hot_time"] > 0) {
    			$content .= $this->formatString(
                    '<tr>
                       <td>Nachisomerisierung</td>
                       <td colspan="2">{value} Minuten</td>
                     </tr>',
                    [
                        'value' => $recipe["post_boil_hot_time"]
                    ]);
        }

        if (($recipe["post_boil_hot_volume"] > 0) || ($recipe["post_boil_roomtemp_volume"] > 0)) {
            $tt = null;
            if ($recipe["post_boil_hot_volume"]) {
                $value = number_format($recipe["post_boil_hot_volume"], 1, ",", ".");
                $label = "Würzemenge nach dem Kochen bei 99 °C";
                $tt = $label . ": " . $value . " Liter (" . number_format($this->lToGal($recipe["post_boil_hot_volume"]), 2, ",", ".") . " gal)" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["post_boil_roomtemp_volume"]) {
                $value = number_format($recipe["post_boil_roomtemp_volume"], 1, ",", ".");
                $label = "Würzemenge nach dem Kochen bei 20 °C";
                $tt = $label . ": " . $value . " Liter (" . number_format($this->lToGal($recipe["post_boil_roomtemp_volume"]), 2, ",", ".") . " gal)" . ($tt ? (",<br/>" . $tt) : "");
            }
            $content .= $this->formatString(
                '<tr>
                   <td>{label}</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{value} Liter</span></td>
                 </tr>',
                [
                    'label' => $label,
                    'value' => $value,
                    'tt' => $tt
                ]);
        }

        if ((count($recipe["yeasts"]) >= 1) || (count($recipe["fermentation_steps"]) >= 1)) {
            if ($recipe["fermentation_steps"][0]["days"]) {
                $state = $recipe["fermentation_steps"][0]["days"] . " Tage";
            }
            if ((!$days) && ($recipe["fermentation_steps"][0]["planned_days"])) {
                $state = $recipe["fermentation_steps"][0]["planned_days"] . " Tage";
            }
            if (($recipe["status"] >= STATUS_FERMENTATION) && ($recipe["status"] < STATUS_SECONDARY)) {
                $d = date_diff(date_create($Sud["Anstelldatum"]), date_create());
                $days = $d->format("%a");
                $state = "bisher " . $days . " Tage";
            }
            $content .= $this->formatString(
                '<tr>
                   <th>Gärung</th>
                   <th colspan="2">{state}</th>
                 </tr>',
                [
                    'state' => $state
                ]);
        }

        if ($recipe["batch_volume"] > 0) {
            $tt = null;
            if ($recipe["planned_batch_volume"] > 0) {
                $value = number_format($recipe["planned_batch_volume"], 1, ",", ".");
                $label = "Geplante Ausschlagmenge";
                $tt = $label . ": " . $value . " Liter (" . number_format($this->lToGal($recipe["planned_batch_volume"]), 2, ",", ".") . " gal)" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["batch_volume"] > 0) {
                $value = number_format($recipe["batch_volume"], 1, ",", ".");
                $label = "Ausschlag- und Anstellmenge";
                $tt = $label . ": " . $value . " Liter (" . number_format($this->lToGal($recipe["batch_volume"]), 2, ",", ".") . " gal)" . ($tt ? (",<br/>" . $tt) : "");
            }
            $content .= $this->formatString(
                '<tr>
                   <td>{label}</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{value} Liter</span></td>
                 </tr>',
                [
                    'label' => $label,
                    'value' => $value,
                    'tt' => $tt
                ]);
        }
        
        if (count($recipe["yeasts"]) >= 1) {
            foreach ($recipe["yeasts"] as $y) {
                $rendered_name = $y["name"];
                if (strlen($y["url"]) >= 1) {
                    $rendered_name = '<a href="' . $y["url"] . '">' . $rendered_name . '</a>';
                }
                if ($y["type"] == YEAST_ALE) {
                    $type = "obergärig";
                } elseif ($y["type"] == YEAST_LAGER) {
                    $type = "untergärig";
                } else {
                    $type = "?";
                }
                if ($y["form"] == YEAST_FORM_DRY) {
                    $form = "trocken";
                } elseif ($y["form"] == YEAST_FORM_LIQUID) {
                    $form = "flüssig";
                } else {
                    $form = "?". $y["type"];
                }
                if ($y["flocculation"] == FLOC_LOW) {
                    $floc = "niedig";
                } elseif ($y["flocculation"] == FLOC_MEDIUM) {
                    $floc = "mittel";
                } elseif ($y["flocculation"] == FLOC_HIGH) {
                    $floc = "hoch";
                } else {
                    $floc = "?";
                }
                if ($y["temp_max"]) {
                    $temp_range = number_format($y["temp_max"], 0, ",", ".");
                    if ($y["temp_min"]) {
                        $temp_range = number_format($y["temp_min"], 0, ",", ".") . " - " . $temp_range;
                    }
                    $temp_range = ", " . $temp_range . " °C";
                } else {
                    $temp_range = "";
                }
                $content .= $this->formatString(
                    '<tr>

                       <td><span data-cmtooltip="{name}, {type}, {form}, Sedimentierung {floc}, max. Endvergärungsgrad {attenuation} %{temp_range}">{rendered_name}</span></td>
                       <td colspan="2">{amount} {unit}</td>
                     </tr>',
                    [
                        'rendered_name' => $rendered_name,
                        'name' => $y["name"],
                        'type' => $type,
                        'form' => $form,
                        'floc' => $floc,
                        'temp_range' => $temp_range,
                        'attenuation' => number_format($y["attenuation"], 0, ",", "."),
                        'amount' => $y["amount"],
                        //'unit' => ($y["unit"] == "packets") && ($y["amount"] == 1) ? "packet" : $y["unit"]
                        'unit' => ($y["unit"] == "packets") ? "Päckchen" : $y["unit"]
                    ]);
            }
        }
        
        $ferm_days = 0;
        if (count($recipe["fermentation_steps"]) >= 1) {
            foreach ($recipe["fermentation_steps"] as $f) {
                $tt_days = null; $tt_temp = null; $days = null; $temp = null;
                if ($f["planned_days"]) {
                    $days = number_format($f["planned_days"], 0, ",", ".");
                    $tt_days = "Geplante Tage: " . $days . ($tt_days ? (",<br/>" . $tt_days) : "");
                    $days = $days . " Tage";
                    $ferm_days += $days;
                }
                if ($f["days"]) {
                    $days = number_format($f["days"], 0, ",", ".");
                    $tt_days = "Tatsächliche Tage: " . $days . ($tt_days ? (",<br/>" . $tt_days) : "");
                    $days = $days . " Tage";
                    $ferm_days += $days;
                }
                if ($f["planned_temp"]) {
                    $temp = number_format($f["planned_temp"], 0, ",", ".");
                    $tt_temp = "Geplante Temperatur: " . $temp . " °C (" . number_format($this->cToF($f["planned_temp"]), 0, ",", ".") . " °F)" . ($tt_temp ? (",<br/>" . $tt_temp) : "");
                    $temp = $temp . " °C";
                }
                if ($f["temp"]) {
                    $temp = number_format($f["temp"], 0, ",", ".");
                    $tt_temp = "Tatsächliche durchschnittliche Temperatur: " . $temp . " °C (" . number_format($this->cToF($f["temp"]), 0, ",", ".") . " °F)" . ($tt_temp ? (",<br/>" . $tt_temp) : "");
                    $temp = $temp . " °C";
                }
                $content .= $this->formatString(
                    '<tr>
                       <td>{name}</t>
                       <td><span data-cmtooltip="{tt_days}">{days}</span></t>
                       <td><span data-cmtooltip="{tt_temp}">{temp}</span></t>
                     </tr>',
                    [
                        'name' => $f["name"],
                        'days' => $days,
                        'temp' => $temp,
                        'tt_days' => $tt_days,
                        'tt_temp' => $tt_temp
                    ]);
            }
        }

        foreach ($recipe["hops"] as $h) {
            if (($h["usage"] < USAGE_PRIMARY) || ($h["usage"] > USAGE_BOTTLE)) continue;
            $rendered_name = $h["name"];
            if (strlen($h["url"]) >= 1) {
                $rendered_name = '<a href="' . $h["url"] . '">' . $rendered_name . '</a>';
            }
            $rendered_type = "";
            switch ($h["type"]) {
            case HOP_LEAF: $rendered_type = ", Dolden"; break;
            case HOP_PLUG: $rendered_type = ", Plugs"; break;
            case HOP_PELLET: $rendered_type = ", Pellets"; break;
            case HOP_EXTRACT: $rendered_type = ", Extrakt"; break;
            default: $rendered_type = ", (?)";
            }
            $rendered_alpha = "";
            /* Alpha not of interest here
            if ($h["alpha"] > 0) {
                $rendered_alpha = ", " . number_format($h["alpha"], 1, ",", ".") . " %α";
            }
            */
            $rendered_time = "";
            switch ($h["usage"]) {
            case USAGE_PRIMARY: $rendered_time = "Hauptgärung"; break;
            case USAGE_SECONDARY: $rendered_time = "Nachgärung"; break;
            case USAGE_KEG: $rendered_time = "Fass"; break;
            case USAGE_BOTTLE: $rendered_time = "Flasche"; break;
            default: $rendered_time = "(?)";
            }
            if ($h["time"] > 0) {
                // $rendered_time .= ", " . $h["time"] . " Tage";
                $rendered_time = $h["time"] . " Tage";
            }
            $dose = number_format($h["amount"] / $recipe["planned_batch_volume"], 1, ",", ".") . " g/l";
			$content .= $this->formatString(
                '<tr>
                   <td>{rendered_name}{rendered_type}{rendered_alpha}, {dose}</td>
                   <td>{amount} g</td>
                   <td>{rendered_time}</td>
                 </tr>',
                [
                    'rendered_name' => $rendered_name,
                    'rendered_type' => $rendered_type,
                    'rendered_alpha' => $rendered_alpha,
                    'amount' => number_format($h["amount"], 0, ",", "."),
                    'rendered_time' => $rendered_time,
                    'dose' => $dose
                ]);
		}

        foreach ($recipe["adjuncts"] as $a) {
            if (($a["usage"] < USAGE_PRIMARY) || ($a["usage"] > USAGE_BOTTLE)) continue;
            $rendered_name = $a["name"];
            if (strlen($a["url"]) >= 1) {
                $rendered_name = '<a href="' . $a["url"] . '">' . $rendered_name . '</a>';
            }
            $rendered_time = "";
            switch ($a["usage"]) {
            case USAGE_PRIMARY: $rendered_time = "Hauptgärung"; break;
            case USAGE_SECONDARY: $rendered_time = "Nachgärung"; break;
            case USAGE_KEG: $rendered_time = "Fass"; break;
            case USAGE_BOTTLE: $rendered_time = "Flasche"; break;
            default: $rendered_time = "(?)";
            }
            $unit = $a["unit"];
            $amount = number_format($a["amount"], 0, ",", ".");
            $dose = number_format($a["amount"] / $recipe["planned_batch_volume"], 3, ",", ".") . " " . $unit . "/l";
            $content .= $this->formatString(
                '<tr>
                       <td>{rendered_name}, {dose}</td>
                       <td>{amount} {unit}</td>
                       <td>{rendered_time}</td>
                     </tr>',
                [
                    'rendered_name' => $rendered_name,
                    'dose' => $dose,
                    'amount' => number_format($a["amount"], ($unit == "g" ? 0 : 3), ",", "."),
                    'unit' => $unit,
                    'rendered_time' => $rendered_time
                ]);
        }

        if ($recipe["fg"] || $recipe["current_g"]) {
            $tt = null;
            if ($recipe["og"] && $recipe["current_g"]) {
                $attenuation = ($this->sgToPlato($recipe["og"]) - $this->sgToPlato($recipe["current_g"])) * 100 / $this->sgToPlato($recipe["og"]);
                $value = number_format($attenuation, 0, ",", ".");
                $label = "Bisheriger scheinbarer Vergärungsgrad";
            }
            if ($recipe["fg"]) {
                $attenuation = ($this->sgToPlato($recipe["og"]) - $this->sgToPlato($recipe["fg"])) * 100 / $this->sgToPlato($recipe["og"]);
                $value = number_format($attenuation, 0, ",", ".");
                $label = "Scheinbarer Endvergärungsgrad";
            }
            $content .= $this->formatString(
                '<tr>
                   <td>{label}</td>
                   <td colspan="2">{value} %</td>
                 </tr>',
                [
                    'label' => $label,
                    'value' => $value
                ]);
        }

        $date = $this->renderDate($recipe["bottle_date"]);
        $content .= $this->formatString(
            '<tr>
               <th>Abfüllung</th>
               <th colspan="2">{date}</th>
             </tr>',
            [
                'date' => $date
            ]);
        
        if ($recipe["containers"]) {
            $content .= $this->formatString(
                '<tr>
                  <td>Gebinde</td>
                  <td colspan="2">{containers}</td>
                </tr>',
                [
                    'containers' => $recipe["containers"]
                ]);
        }

        if ($recipe["pack_color"]) {
            $content .= $this->formatString(
                '<tr>
                  <td>Kronkorkenfarbe</td>
                  <td colspan="2">{pack_color}</td>
                </tr>',
                [
                    'pack_color' => $recipe["pack_color"]
                ]);
        }

        if (($recipe["planned_co2"]) || ($recipe["co2"])) {
            $tt = null;
            if ($recipe["planned_co2"]) {
                $value = number_format($recipe["planned_co2"], 1, ",", ".");
                $label = "Geplante Karbonisierung";
                $bar4 = $this->calcBarAtC($recipe["planned_co2"], 4);
                $bar20 = $this->calcBarAtC($recipe["planned_co2"], 20);
                $psi38 = $this->calcPsiAtF($recipe["planned_co2"], 38);
                $psi68 = $this->calcPsiAtF($recipe["planned_co2"], 68);
                $vols = $this->co2gToVols($value);
                $tt = $label . ": " . $value . " g/l CO2 <br/> ≙ " . number_format($bar4, 1, ",", ".") . "-" . number_format($bar20, 1, ",", ".") . " bar bei 4-20 °C <br/> ≙ " . number_format($psi38, 1, ",", ".") . "-" . number_format($psi68, 1, ",", ".") . " psi bei 38-68 °F <br/> ≙ " . number_format($vols, 1, ",", ".") . " volumes" . ($tt ? (",<br/>" . $tt) : "");
            }
            if ($recipe["co2"]) {
                $value = number_format($recipe["co2"], 1, ",", ".");
                $label = "Gemessene Karbonisierung";
                $bar4 = $this->calcBarAtC($recipe["co2"], 4);
                $bar20 = $this->calcBarAtC($recipe["co2"], 20);
                $psi38 = $this->calcPsiAtF($recipe["co2"], 38);
                $psi68 = $this->calcPsiAtF($recipe["co2"], 68);
                $vols = $this->co2gToVols($value);
                $tt = $label . ": " . $value . " g/l CO2 <br/> ≙ " . number_format($bar4, 1, ",", ".") . "-" . number_format($bar20, 1, ",", ".") . " bar bei 4-20 °C <br/> ≙ " . number_format($psi38, 1, ",", ".") . "-" . number_format($psi68, 1, ",", ".") . " psi bei 38-68 °F <br/> ≙ " . number_format($vols, 1, ",", ".") . " volumes" . ($tt ? (",<br/>" . $tt) : "");
            }
            $content .= $this->formatString(
                '<tr>
                   <td>{label}</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{value} g/l CO2</span></td>
                 </tr>',
                [
                    'label' => $label,
                    'value' => $value,
                    'tt' => $tt
                ]);
        }
        
        if ($recipe["age_days"]) {
            if (($recipe["age_days"] % 7) == 0) {
                $age = number_format($recipe["age_days"] / 7, 0, ",", ".") . " Wochen";
            } else {
                $age = number_format($recipe["age_days"], 0, ",", ".") . " Tage";
            }
            $d = null;
            $tt = null;
            if ($recipe["bottle_date"]) {
                $d = new DateTime($this->renderDate($recipe["bottle_date"]));
                date_add($d, new DateInterval('P' . $recipe["age_days"] . 'D'));
                $tt = "trinkfertig: " . $this->renderDate($d->format("Y-m-d"));
            } elseif ($recipe["brew_date"] && ($ferm_days > 0)) {
                $d = new DateTime($this->renderDate($recipe["brew_date"]));
                date_add($d, new DateInterval('P' . $ferm_days . 'D'));
                date_add($d, new DateInterval('P' . $recipe["age_days"] . 'D'));
                $tt = "trinkfertig: " . $this->renderDate($d->format("Y-m-d"));
            }
    		$content .= $this->formatString(
                '<tr>
                   <td>geplante Reifezeit</td>
                   <td colspan="2"><span data-cmtooltip="{tt}">{age}</span></td>
                 </tr>',
                [
                    'age' => $age,
                    'tt' => $tt
                ]);
        }
        
        $content .= $this->formatString(
            '<tr><th>Metadaten</th><th colspan="2"></th></tr>
             {tr_brewer}
             {tr_cobrewer}
             {tr_software}
             {tr_equipment}
             <tr><td>Letztes Update</td><td colspan="2">{updated_at}</td></tr>
           </table',
            [
                'tr_brewer' => $recipe["brewer"] ? '<tr><td>Brauer</td><td colspan="2">' . $recipe["brewer"] : "</td></tr>",
                'tr_cobrewer' => $recipe["assistant"] ? '<tr><td>Co-Brauer</td><td colspan="2">' . $recipe["assistant"] : "</td></tr>",
                'tr_software' => $recipe["source"] ? '<tr><td>Software / Datenquelle</td><td colspan="2">' . $recipe["source"] : "</td></tr>",
                'tr_equipment' => $recipe["equipment"] ? '<tr><td>Equipment</td><td colspan="2">' . $recipe["equipment"] : "</td></tr>",
                'updated_at' => $this->renderDate($recipe["updated_at"])
            ]);
        
        return $content;
    }


    
    function extractText($text, $tag, $default) {
        $value = $default;
        if (strpos($text, '[[' . $tag) !== false) {
            $value = preg_replace('/^.*\[\[' . $tag . ' *:([^\]]*)\]\].*$/s', '$1', $text);
            $value = preg_replace('/\+/s', '<br/>', $value);
        }
        // $value = htmlentities($value);
        $value = trim($value);
        return $value;
    }



    function kbh_recipe_shortcode($atts) {

    	$a = shortcode_atts (array(
    		'title' => null,
    		'select' => null
        ), $atts);
    	$select = $a['select'];
    	if ($a['title']) {
    		$select = "Sudname LIKE '" . $a['title'] . "'";
    	}
        
    	$location = get_option('wp_brewing_kbh_location', '/root/.kleiner-brauhelfer/kb_daten.sqlite');
    	$cache = get_option('wp_brewing_kbh_cache', 3600);
    	if (strpos($location, '//') !== false) {
    	  $path = get_temp_dir() . "/wp-brewing-kbh.sqlite";
              if ((!file_exists($path)) || (time()-filemtime($location) > $cache)) {
      	    $response = wp_remote_get($location);
     	    if (is_array($response)) {
    	      $data = $response['body'];
    	      file_put_contents($path, $data);
    	      $location = $path;
    	    } else {
    	      return '[embedding recipe failed.] <!-- could not load KBH DB from ' . $location . ' -->';
                }
              }
    	}

    	$db = new SQLite3($location);

    	$query = "SELECT * FROM Ausruestung";
    	$dbausr = $db->query($query);
        $ausruestung = null;
    	while ($Ausruestung = $dbausr->fetchArray()) {
            $estimated_sudhausausbeute = $Ausruestung["Sudhausausbeute"];
            $ausruestung = $Ausruestung["Name"];
        }
        $query = "SELECT * FROM Sud WHERE " . $select;
        $dbsude = $db->query($query);
    	while ($Sud = $dbsude->fetchArray()) {
    		$sudid = $Sud["ID"];
    		$query = "SELECT * FROM Hauptgaerverlauf WHERE SudID = " . $sudid . " ORDER BY Zeitstempel";
    		$dbgaerverlauf = $db->query($query);
    		$restextrakt = null;
            if ($Sud["SWAnstellen"] > 0) {
                $restextrakt = $Sud["SWAnstellen"];
            }
            $i = 0; $primary_end = ""; $temp_sum = 0;
    		while ($entry = $dbgaerverlauf->fetchArray()) {
                if ($entry["Zeitstempel"] > $primary_end) {
                    $primary_end = $entry["Zeitstempel"];
                }
                if ((($entry["SW"] < $restextrakt) || (! $restextrakt)) && ($entry["SW"] > 0)) {
                    $restextrakt = $entry["SW"];
                }
                if ($entry["Temp"] > 0) {
                    $temp_sum += $entry["Temp"];
                    $i += 1;
                }
    		}
            if ($i > 0) {
                $primary_temp = $temp_sum / $i;
            } else {
                $primary_temp = null;
            }
            if ($Sud["BierWurdeAbgefuellt"]) {
                $d = date_diff(date_create($Sud["Anstelldatum"]), date_create($Sud["Abfuelldatum"]));
                $primary_days = $d->format("%a");
            } elseif ($primary_end) {
                $d = date_diff(date_create($Sud["Anstelldatum"]), date_create($primary_end));
                $primary_days = $d->format("%a");
            } else {
                $primary_days = null;
            }
            $query = "SELECT * FROM Nachgaerverlauf WHERE SudID = " . $sudid . " ORDER BY Zeitstempel";
    		$dbnachgaerverlauf = $db->query($query);
    		$co2 = null;
            $i = 0; $secondary_end = null; $temp_sum = 0;
    		while ($entry = $dbnachgaerverlauf->fetchArray()) {
                if ($entry["Zeitstempel"] > $secondary_end) {
                    $secondary_end = $entry["Zeitstempel"];
                }
                if (($entry["CO2"] > $co2) && ($entry["Druck"] > 0)) {
                    $co2 = $entry["CO2"];
                }
                if ($entry["Temp"] > 0) {
                    $temp_sum += $entry["Temp"];
                    $i += 1;
                }
    		}
            if ($i > 0) {
                $secondary_temp = $temp_sum / $i;
            } else {
                $secondary_temp = null;
            }
    		$query = "SELECT * FROM Malzschuettung WHERE SudID = " . $sudid . " ORDER BY Prozent DESC";
    		$dbschuettung = $db->query($query);
    		$query = "SELECT * FROM WeitereZutatengaben WHERE SudID = " . $sudid . " AND Zeitpunkt = 2 ORDER BY erg_Menge DESC";
    		$dbweiteremaischgaben = $db->query($query);
    		$query = "SELECT * FROM Rasten WHERE SudID = " . $sudid;
    		$dbrasten = $db->query($query);
    		$query = "SELECT * FROM Hopfengaben WHERE SudID = " . $sudid . " AND Vorderwuerze = 1 ORDER BY erg_Menge DESC";
    		$dbvwh = $db->query($query);
    		$query = "SELECT * FROM Hopfengaben WHERE SudID = " . $sudid . " AND Vorderwuerze = 0 ORDER BY Zeit DESC";
    		$dbhopfen = $db->query($query);
    		$query = "SELECT * FROM WeitereZutatengaben WHERE SudID = " . $sudid . " AND Zeitpunkt = 1 ORDER BY erg_Menge DESC";
    		$dbweiterekochgaben = $db->query($query);
    		$query = "SELECT * FROM WeitereZutatengaben WHERE SudID = " . $sudid . " AND Zeitpunkt = 0 ORDER BY erg_Menge DESC";
    		$dbgaergaben = $db->query($query);


            $query = "SELECT * FROM WeitereZutatenGaben WHERE SudID = " . $sudid . " AND Typ != 100 AND Ausbeute > 0 ORDER BY erg_Menge DESC";
            $dbotherferms = $db->query($query);
            $query = "SELECT * FROM WeitereZutatenGaben WHERE SudID = " . $sudid . " AND ( Typ = 100 OR Typ = -1 ) AND Zeitpunkt = 0 ORDER BY erg_Menge DESC";
    		$dbdryhop = $db->query($query);
            
            // $query = "SELECT * FROM WeitereZutatenGaben WHERE SudID = " . $sudid . " AND Typ != 100 AND Typ != -1 AND Ausbeute <= 0 ORDER BY erg_Menge DESC";
            $query = "SELECT * FROM WeitereZutatenGaben WHERE SudID = " . $sudid . " ORDER BY erg_Menge DESC";
    		$dbadjuncts = $db->query($query);
            
            $d = date_diff(date_create($Sud["Anstelldatum"]), date_create());
    		$GaertageBisher = $d->format("%a");
    		$d = date_diff(date_create($Sud["Abfuelldatum"]), date_create());
    		$NachgaertageBisher = $d->format("%a");
            /* aus https://brauerei.mueggelland.de/vergaerungsgrad.html */
    		$og = $Sud["SWAnstellen"];
    		$wfg = 0.1808 * $og + 0.1892 * $restextrakt;
    		$d = 261.1 / (261.53 - $restextrakt);
    		$abw = ($og - $wfg) / (2.0665 - 0.010665 * $og);
    		$kcal = round((6.9 * $abw + 4 * ( $wfg - 0.1 )) * 10 * 0.1 * $d);

            $today = date("o-m-d");
            $recipe = [
                "name" => $Sud["Sudname"],
                "source" => "Kleiner Brauhelfer",
                "equipment" => $ausruestung,
                "brewer" => $this->extractText($Sud["Kommentar"], "Brauer", null),
                "assistant" => $this->extractText($Sud["Kommentar"], "Assistent", null),
                "bjcp2015_style_id" => $this->extractText($Sud["Kommentar"], "BJCP-Style", null),
                "type" => RECIPE_TYPE_ALLGRAIN,
                "description" => strlen($Sud["Kommentar"]) > 0 ? explode(PHP_EOL, $Sud['Kommentar'])[0] : null,
                "notes" => null, // TBD
                "created_at" => $Sud["Erstellt"],
                "updated_at" => $Sud["Gespeichert"],
                "brew_date" => $Sud["Braudatum"] ? $this->localToUtc($Sud["Braudatum"]) : null,
                "bottle_date" => $Sud["BierWurdeAbgefuellt"] ? $this->localToUtc($Sud["Abfuelldatum"]) : null,
                "emptied_date" => null,
                "planned_batch_volume" => $Sud["Menge"], // l, into fermenter at 20 °C
                "bottled_volume" => $Sud["BierWurdeAbgefuellt"] ? $Sud["erg_AbgefuellteBiermenge"] : null, // bottled or keged
                "planned_og" => $this->platoToSg($Sud["SW"]),
                "og" => $Sud["BierWurdeGebraut"] ? $this->platoToSg($Sud["SWAnstellen"]) : null,
                "estimated_fg" => null, // TBD
                "fg" => (($restextrakt > 0) && ($Sud["BierWurdeAbgefuellt"])) ? $this->platoToSg($restextrakt) : null,
                "current_g" => (($restextrakt > 0) && ($Sud["BierWurdeGebraut"]) && (! $Sud["BierWurdeAbgefuellt"])) ? $this->platoToSg($restextrakt) : null, // TBD
                "abv" => $Sud["BierWurdeAbgefuellt"] ? $Sud["erg_Alkohol"] : null, // %vol
                "ibu" => $Sud["IBU"], // IBU
                "ebc" => $Sud["erg_Farbe"], // EBC
                "calories" => $kcal > 0 ? $kcal : null, // kcal/100ml
                //"planned_co2" => str_replace(",", ".", preg_replace("~[^0-9.,]~i", "", $this->extractText($Sud["Kommentar"], "CO2", null))), // g/l
                "planned_co2" => str_replace(",", ".", preg_replace("~[^0-9.,]~i", "", $Sud["CO2"])), // g/l
                "co2" => $co2 > 0 ? $co2 : null, // CO2 g/l
                "drink_temp" => str_replace(",", ".", preg_replace("~[^0-9.,]~i", "", $this->extractText($Sud["Kommentar"], "Trinktemperatur", null))), // °C
                "song" => $this->extractText($Sud["Kommentar"], "Song", null), // "Song zum Bier" :-) (free text)
                "stock" => $this->extractText($Sud["Kommentar"], "Restbestand", null), // "Restbestand" (free text)
                "containers" => $this->extractText($Sud["Kommentar"], "Gebinde", null), // "Gebinde" (free text)
                "pack_color" => $this->extractText($Sud["Kommentar"], "Kronkorkenfarbe", null), // "Kronkorkenfarbe" (free text)
                "age_days" => $Sud["Reifezeit"] * 7,
                //"fermentables_total" => $Sud["erg_S_Gesammt"], // kg -- should be calculated
                "planned_residual_alkalinity" => $this->dhToPpm($Sud["RestalkalitaetSoll"]), // ppm
                "mash_in_temp" => $Sud["EinmaischenTemp"], // °C
                "mash_water_volume" => $Sud["erg_WHauptguss"], // l
                "sparge_water_volume" => $Sud["erg_WNachguss"], // l
                "boil_time" => $Sud["KochdauerNachBitterhopfung"], // min
                "estimated_sudhausausbeute" => $estimated_sudhausausbeute > 0 ? $estimated_sudhausausbeute : null, // %
                "sudhausausbeute" => $Sud["BierWurdeGebraut"] ? $Sud["erg_Sudhausausbeute"] : null, // %
                "post_boil_hot_time" => $Sud["Nachisomerisierungszeit"],
                //"post_boil_hot_volume" => $Sud["WuerzemengeKochende"], // l at 99 °C TBD: KBH value is at which temp? estimated or actual?  -- calculated?
                "post_boil_roomtemp_volume" => $Sud["BierWurdeGebraut"] ? $Sud["WuerzemengeVorHopfenseihen"] : null, // l, theoretically at 20 °C
                "batch_volume" => $Sud["BierWurdeGebraut"] ? $Sud["WuerzemengeAnstellen"] : null,
                "fermentables" => [],
                "mash_steps" => [],
                "hops" => [],
                "adjuncts" => [],
                "yeasts" => [],
                "fermentation_steps" => [],
                "status" => ($Sud["BierWurdeGebraut"]) ? (($Sud["BierWurdeVerbraucht"]) ? STATUS_EMPTIED : (($Sud["BierWurdeAbgefuellt"]) ? (       (date_add(new DateTime($this->renderDate($this->localToUtc($Sud["Abfuelldatum"]))), new DateInterval('P' . 7 * $Sud["Reifezeit"] . 'D'))->format("Y-m-d") < date("o-m-d"))       ? STATUS_COMPLETE : STATUS_CONDITIONING) : STATUS_FERMENTATION)) : ($this->localToUtc($Sud["Braudatum"] == date("o-m-d") ? STATUS_BREWDAY : STATUS_PREPARING))
                // "estimated_attenuation"  // should be calculated (or just copied from yeast?)
                // "attenuation"  // should be calculated from og and fg
            ];

            // fermentables
    		while ($malz = $dbschuettung->fetchArray()) {
                $name = $malz["Name"];
                $query = 'SELECT * FROM Malz WHERE Beschreibung = "' . $name . '"';
                $result = $db->query($query);
                $url = null;
                if ($result) {
                    while ($row = $result->fetchArray()) {
                        if (strlen($row["Link"]) >= 1) {
                            $url = $row["Link"];
                        }
                    }
                }
                array_push($recipe["fermentables"], [
                    "name" => $name,
                    "usage" => USAGE_MASH,
                    "url" => $url,
                    "amount" => $malz["erg_Menge"] // kg
                    // percentage to be calculated
                ]);
            }
            
            // mash_steps
    		while ($rast = $dbrasten->fetchArray()) {
                array_push($recipe["mash_steps"], [
                    "name" => $rast["RastName"],
                    "temp" => $rast["RastTemp"], // °C
                    "time" => $rast["RastDauer"] // minutes
                ]);
            }
            
            // hops
    		while ($hopfen = $dbvwh->fetchArray()) {
                $name = $hopfen["Name"];
                $query = 'SELECT * FROM Hopfen WHERE Beschreibung = "' . $name . '"';
                $result = $db->query($query);
                $url = null;
                if ($result) {
                    while ($row = $result->fetchArray()) {
                        if (strlen($row["Link"]) >= 1) {
                            $url = $row["Link"];
                        }
                    }
                }
                array_push($recipe["hops"], [
                    "name" => $hopfen["Name"],
                    "url" => $url,
                    "type" => $hopfen["Pellets"] == 1 ? HOP_PELLET : HOP_LEAF,
                    "usage" => USAGE_FIRSTWORT,
                    "alpha" => $hopfen["Alpha"] > 0 ? $hopfen["Alpha"] : null,
                    "time" => null,
                    "amount" => $hopfen["erg_Menge"] // g
                ]);
            }
    		while ($hopfen = $dbhopfen->fetchArray()) {
                $name = $hopfen["Name"];
                $query = 'SELECT * FROM Hopfen WHERE Beschreibung = "' . $name . '"';
                $result = $db->query($query);
                $url = null;
                if ($result) {
                    while ($row = $result->fetchArray()) {
                        if (strlen($row["Link"]) >= 1) {
                            $url = $row["Link"];
                        }
                    }
                }
                if ($hopfen["Zeit"] == 0) {
                    $usage = USAGE_FLAMEOUT;
                } elseif ($hopfen["Zeit"] < 0) {
                    $usage = USAGE_WHIRLPOOL;
                } else {
                    $usage = USAGE_BOIL;
                }
                array_push($recipe["hops"], [
                    "name" => $hopfen["Name"],
                    "url" => $url,
                    "type" => $hopfen["Pellets"] == 1 ? HOP_PELLET : HOP_LEAF,
                    "usage" => $usage,
                    "alpha" => $hopfen["Alpha"] > 0 ? $hopfen["Alpha"] : null,
                    "time" => $hopfen["Zeit"],
                    "amount" => $hopfen["erg_Menge"] // g
                ]);
    		}
            // dry hop
    		while ($hopfen = $dbdryhop->fetchArray()) {
                $name = $hopfen["Name"];
                $query = 'SELECT * FROM Hopfen WHERE Beschreibung = "' . $name . '"';
                $result = $db->query($query);
                $url = null;
                $type = null;
                $alpha = null;
                if ($result) {
                    while ($row = $result->fetchArray()) {
                        if (strlen($row["Link"]) >= 1) {
                            $url = $row["Link"];
                            $type = $row["Pellets"] == 1 ? HOP_PELLET : HOP_LEAF;
                            $alpha = $row["Alpha"];
                        }
                    }
                }
                $time = $hopfen["Zugabedauer"];
                if ($time >= 1440) {
                    $time = $time / 1440;
                } elseif ($time == 0) {
                    $time = null;
                }
                array_push($recipe["hops"], [
                    "name" => $name,
                    "url" => $url,
                    "type" => $type,
                    "usage" => USAGE_PRIMARY,
                    "alpha" => null,
                    "time" => $time,
                    "amount" => $hopfen["erg_Menge"] // g
                ]);
    		}

            // adjuncts
    		while ($adjunct = $dbadjuncts->fetchArray()) {
                $name = $adjunct["Name"];
                $query = 'SELECT * FROM WeitereZutaten WHERE Beschreibung = "' . $name . '"';
                $result = $db->query($query);
                $url = null;
                $type = null;
                $time = $adjunct["Zugabedauer"];
                if ($time >= 1440) {
                    $time = $time / 1440;
                } elseif ($time == 0) {
                    $time = null;
                }
                if ($adjunct["Zeitpunkt"] == 2) {
                    $usage = USAGE_MASH;
                } elseif ($adjunct["Zeitpunkt"] == 1) {
                    if ($adjunct["Zugabedauer"] == 0) {
                        $usage = USAGE_FLAMEOUT;
                    } elseif ($adjunct["Zugabedauer"] < 0) {
                        $usage = USAGE_WHIRLPOOL;
                    } else {
                        $usage = USAGE_BOIL;
                    }
                } elseif ($adjunct["Zeitpunkt"] == 0) {
                    $usage = USAGE_PRIMARY;
                }
                $unit = "g";
                $unit_factor = 1;
                if ($result) {
                    while ($row = $result->fetchArray()) {
                        if (strlen($row["Link"]) >= 1) {
                            $url = $row["Link"];
                        }
                        if ($row["Einheiten"] != 1) {
                            $unit = "kg";
                            $unit_factor = 0.001;
                        }
                    }
                }
                // TBD: $type = ...
                array_push($recipe["adjuncts"], [
                    "name" => $name,
                    "url" => $url,
                    "type" => $type,
                    "usage" => $usage,
                    "time" => $time,
                    "amount" => $adjunct["erg_Menge"] * $unit_factor,
                    "unit" => $unit
                ]);
            }
            usort($recipe["adjuncts"], 'adjuncts_cmp');

            // yeast
            $name = $Sud["AuswahlHefe"];
            $url = null;
            $type = null;
            $form = null;
            $flocculation = null;
            $attenuation = null;
            $temp_min = null;
            $temp_max = null;
            $query = 'SELECT * FROM Hefe WHERE Beschreibung = "' . $name . '"';
            $result = $db->query($query);
            if ($result) {
                while ($row = $result->fetchArray()) {
                    $url = (strlen($row["Link"]) >= 1) ? $row["Link"] : null;
                    $type = ($row["TypOGUG"] == 1) ? YEAST_ALE : YEAST_LAGER;
                    $form = ($row["TypTrFl"] == 1) ? YEAST_FORM_DRY : YEAST_FORM_LIQUID;
                    if ($row["SED"] == 1) {
                        $flocculation = FLOC_HIGH;
                    } elseif ($row["SED"] == 2) {
                        $flocculation = FLOC_MEDIUM;
                    } elseif ($row["SED"] == 3) {
                        $flocculation = FLOC_LOW;
                    }
                    $attenuation = ($row["EVG"] > 0) ? $row["EVG"] : null; // %
                    if ($row["Temperatur"]) {
                        $matches = array();
                        preg_match('/^[^0-9]*([0-9]+)[^0-9]+([0-9]*).*$/', $row["Temperatur"], $matches);
                        if (count($matches) >= 1) {
                            $temp_min = $matches[1];
                            if ((count($matches) >= 2) and (strlen($matches[2]) > 0)) {
                                $temp_max = $matches[2];
                            } else {
                                $temp_max = $temp_min;
                            }
                        }
                    }
                }
            }
            array_push($recipe["yeasts"], [
                "name" => $name,
                "url" => $url,
                "type" => $type,
                "form" => $form,
                "attenuation" => $attenuation,
                "flocculation" => $flocculation,
                "temp_min" => $temp_min,
                "temp_max" => $temp_max,
                "unit" => "packets",
                "amount" => $Sud["HefeAnzahlEinheiten"]
            ]);
            
            // fermentation
            array_push($recipe["fermentation_steps"], [
                "name" => "Hauptgärung",
                "planned_days" => null,
                "planned_temp" => null,
                "days" => $primary_days,
                "temp" => $primary_temp // °C
            ]);
            if ($secondary_temp > 0) {
                array_push($recipe["fermentation_steps"], [
                    "name" => "Nachgärung",
                    "planned_days" => null,
                    "planned_temp" => null,
                    "days" => $secondary_days,
                    "temp" => $secondary_temp // °C
                ]);
            }
            // explicit fermentation info
            $ferms = $this->extractText($Sud["Kommentar"], "Fermentation", null);
            $ferms = explode(",", $ferms);
            $i = 0;
            foreach ($ferms as $ferm) {
                $name = null; $days = null; $temp = null;
                $a = explode("@", $ferm);
                $temp = $a[1];
                $a = explode(":", $a[0]);
                if (count($a) == 1) {
                    $days = $a[0];
                } else {
                    $name = $a[0];
                    $days = $a[1];
                }
                if ($name) {
                    $recipe["fermentation_steps"][$i]["name"] = $name;
                }
                if ($days > 0) {
                    $recipe["fermentation_steps"][$i]["planned_days"] = $days;
                }
                if ($temp > 0) {
                    $recipe["fermentation_steps"][$i]["planned_temp"] = $temp;
                }
                $i += 1;
            }
        }
        
    	$db->close();

        $content = $this->renderRecipe($recipe);
        
    	return $content;
    }


    
    function kbh_recipes_shortcode($atts) {

        $att = shortcode_atts (array(
            'mode' => null,
            'year' => null
        ), $atts);

        return $this->kbh_process_table($att["mode"], $att["year"]);
    }



    function zahlWort($int) {
        $int = (string)(int)$int;
        $z2w = array('null'=>'null', 'und'=>'und',
                     1=>array('ein','ein','zwei','drei','vier','fünf','sechs','sieben','acht','neun','zehn',
                              'elf','zwölf','dreizehn','vierzehn','fünfzehn','sechzehn','siebzehn','achtzehn','neunzehn'),
                     2=>array('zwanzig','dreissig','vierzig','fünfzig','sechzig', 'siebzig','achtzig','neunzig'),
                     3=>array('hundert','tausend')
        );
        $intrev = strrev($int);
        $len = strlen($intrev); // Stellen
        $zif = str_split($intrev); // Ziffern
        $zif = array_map('intval', $zif);
        $wort = '';
  
        if($len===1){ # Einstellige Zahl
            if($zif[0]===0) $wort .= $z2w['null']; // 0
            elseif($zif[0]===1) $wort .= $z2w[1][0]; // 1
            else $wort .= $z2w[1][$zif[0]]; // 2 bis 9
        }
        elseif($len===2){ # Zweistellige Zahl
            if($zif[1]===1) $wort .= $z2w[1][$zif[0]+10]; // 10 bis 19
            elseif($zif[1]>=2 & $zif[0]!==0) $wort .= $z2w[1][$zif[0]].$z2w['und']; // [2-9][1-9]
            if($zif[1]>=2) $wort .= $z2w[2][$zif[1]-2]; // 20 bis 99
        }
        elseif($len===3){ # Dreistellige Zahl
            $wort .= $z2w[1][$zif[2]].$z2w[3][0]; // 100 bis 999
            if($zif[1]===0 & $zif[0]==1) $wort .= $z2w[1][0]; // n01
            elseif($zif[1]===0 & $zif[0]!==0) $wort .= $z2w[1][$zif[0]]; // n02 bis n09
            elseif($zif[1]===1) $wort .= $z2w[1][$zif[0]+10]; // n10 bis n19
            elseif($zif[1]>=2 & $zif[0]!=0) $wort .= $z2w[1][$zif[0]].$z2w['und']; // n[2-9][1-9]
            if($zif[1]>=2) $wort .= $z2w[2][$zif[1]-2]; // n20 bis n99
        }
        elseif($len===4){ # Vierstellige Zahl
            $wort .= $z2w[1][$zif[3]].$z2w[3][1]; // 1000 bis 9999
            if($zif[2]!==0) $wort .= $z2w[1][$zif[2]].$z2w[3][0]; // n100 bis n999
            elseif($zif[1]!==0 || $zif[0]!==0) $wort .= $z2w['und']; // n0[1-9][1-9]
            if($zif[1]===0 & $zif[0]===1) $wort .= $z2w[1][0]; // nn01
            elseif($zif[1]===0 & $zif[0]!==0) $wort .= $z2w[1][$zif[0]]; // nn02 bis nn09
            elseif($zif[1]===1) $wort .= $z2w[1][$zif[0]+10]; // nn10 bis nn19
            elseif($zif[1]>=2 & $zif[0]!==0) $wort .= $z2w[1][$zif[0]].$z2w['und']; // nn[2-9][1-9]
            if($zif[1]>=2) $wort .= $z2w[2][$zif[1]-2]; // nn20 bis nn99
        }
  
        return $wort;        
    }


    
    function kbh_process_table($mode, $year) {
        
        $content = "";

        if ($year) {
            $where = ' WHERE strftime("%Y",Braudatum) = "' . $year . '" AND BierWurdeGebraut = 1';
        } else {
            $where = "";
        }
        
        $location = get_option('wp_brewing_kbh_location', '/root/.kleiner-brauhelfer/kb_daten.sqlite');
        $category = get_option('wp_brewing_category', 'Sude');
        
        $cache = get_option('kleiner_brauhelfer_cache', 3600);
        if (strpos($location, '//') !== false) {
            $path = get_temp_dir() . "/wp-brewing-kbh.sqlite";
            if ((!file_exists($path)) || (time()-filemtime($location) > $cache)) {
                $response = wp_remote_get($location);
                if (is_array($response)) {
                    $data = $response['body'];
                    file_put_contents($path, $data);
                    $location = $path;
                } else {
                    return '[embedding recipe failed.] <!-- could not load KBH DB from ' . $location . ' -->';
                }
            }
        }

        $db = new SQLite3($location);

        $query = "SELECT * FROM Sud" . $where . " ORDER BY Braudatum";
        $dbsude = $db->query($query);
        $query = new WP_Query( array(
            'posts_per_page' => 1000,
            'category_name' => $category ) );
        $posts = [];
        while ( $query->have_posts() ) {
        	$query->the_post();
            $post = [ "title" => get_the_title(), "url" => get_post_permalink() ];
            array_push($posts, $post);
        }
        if ($mode != "xml") {
            $content .= '
    <table class="wp-brewing-recipes">
      <style type="text/css">
        table.wp-brewing-recipes { font-size:11pt; }
        table.wp-brewing-recipes tr th { background:white; color:#333; padding-left:4px; width:60% }
        table.wp-brewing-recipes tr th+th { background:white; color:#333; width:20%; text-align:center; padding-right:4px }
        table.wp-brewing-recipes tr th+th+th { background:white; color:#333; width:25%; text-align:right; padding-right:4px }
        table.wp-brewing-recipes tr td+td { text-align:center; }
        table.wp-brewing-recipes tr td+td+td { text-align:right; width:25%; }
        /* fix glossary link color on white heading line */
        table.wp-brewing-recipes tr th a.glossaryLink { color:#333 }
        table.wp-brewing-recipes tr th a.glossaryLink:hover { color:#333 }
      </style>';
            if ($mode == "steuer") {
                $content .= '
      <tr>
        <th>Datum</th>
        <th>°P</th>
        <th>€/(hl°P)</th>
        <th>€/hl</th>
        <th>hl</th>
        <th>€</th>
      </tr>';
            } else {
                $content .= '
      <tr>
        <th>Bezeichnung</th>
        <th>Status</th>
        <th>Datum</th>
      </tr>';
            }
        } else {
            $content = $this->formatString('<?xml version="1.0" encoding="UTF-8"?>
<xml-data xmlns="http://www.lucom.com/ffw/xml-data-1.0.xsd">
    <form>catalog://Unternehmen/vst/bier/2075</form>
    <instance>
        <datarow>
            <element id="ID_USER">.anonymous</element>
            <element id="name_firma">{name_firma}</element>
            <element id="strasse_nr">{strasse_nr}</element>
            <element id="plz_ort">{plz_ort}</element>
            <element id="ansprechpartner">{ansprechpartner}</element>
            <element id="telefon">{telefon}</element>
            <element id="email">{email}</element>
            <element id="hza">{hza}</element>
            <element id="hza_anschrift">{hza_anschrift}</element>
            <element id="steuerlagennummer">{steuerlagernummer}</element>
            <element id="k1">false</element>
            <element id="k2">false</element>
            <element id="k3">false</element>
            <element id="k4">false</element>
            <element id="k5">false</element>
            <element id="k6">false</element>
            <element id="k7">false</element>
            <element id="k8">false</element>
            <element id="k9">false</element>
            <element id="k10">false</element>
            <element id="k11">false</element>
            <element id="k12">true</element>
            <element id="k14">false</element>
            <element id="k15">false</element>', [
                'name_firma' => str_replace(', ', "\n", get_option('wp_brewing_2075_name_firma', '')),
                'strasse_nr' => current_user_can('administrator') ? get_option('wp_brewing_2075_strasse_nr', '') : "",
                'plz_ort' => current_user_can('administrator') ? get_option('wp_brewing_2075_plz_ort', '') : "",
                'ansprechpartner' => get_option('wp_brewing_2075_ansprechpartner', ''),
                'telefon' => current_user_can('administrator') ? get_option('wp_brewing_2075_telefon', '') : "",
                'email' => current_user_can('administrator') ? get_option('wp_brewing_2075_email', '') : "",
                'hza' => get_option('wp_brewing_2075_hza', ''),
                'hza_anschrift' => str_replace(', ', "\n", get_option('wp_brewing_2075_hza_anschrift', '')),
                'steuerlagernummer' => current_user_can('administrator') ? get_option('wp_brewing_2075_steuerlagernummer', '') : ""
            ]);
        }
        $gesamtbetrag = 0;
        $n = 0;
        while ($Sud = $dbsude->fetchArray()) {
            if ($Sud["SW"] >= 5) {
		        $m = $Sud["BierWurdeAbgefuellt"] ? $Sud["erg_AbgefuellteBiermenge"] : $Sud["Menge"];
                $Gesamt += $m;
            }
            if ($Gesamt <= 200 && (($mode == "steuer") || ($mode == "xml"))) {
                continue;
            }
            /* find best matching WP post by comparing title prefix lengths */
            $href = null; $maxl = 0;
            foreach ($posts as $post) {
                for ($l = 1; $l < strlen($Sud["Sudname"]); $l++) {
                    $sub = substr($Sud["Sudname"], 0, $l);
                    if (strpos($post["title"], $sub) !== false) {
                        if ($l > $maxl) {
                            $maxl = $l;
                            $href = $post["url"];
                        }
                    }
                }
            }
            if (($mode == "steuer") || ($mode == "xml")) {
                $n += 1;
		        $steuersatz = 0.4407;
		        $swfloor = $Sud["BierWurdeGebraut"] ? floor($Sud["SWAnstellen"]) : floor($Sud["SW"]);
                $litersatz = floor($steuersatz * $swfloor * 100) / 100;
                $mengehl = floor($m) / 100;
                $betrag = floor($mengehl * $litersatz * 100) / 100;
                $gesamtbetrag += $betrag;
                if ($swfloor >= 1.0) {
                    if ($mode != "xml") {
                        $content .= $this->formatString('
        <tr>
          <td><a href="{href}">{Braudatum}</a></td>
          <td>{Stammwuerze}</td>
          <td>{Steuersatz}</td>
          <td>{Litersatz}</td>
          <td>{Menge}</td>
          <td>{Betrag}</td>
        </tr>', [
            'href' => $href,
            'Sudname' => $Sud["Sudname"],
            'Braudatum' => $Sud["Braudatum"],
            'Stammwuerze' => $swfloor,
            'Steuersatz' => number_format($steuersatz, 4, ",", "."),
            'Litersatz' => number_format($litersatz, 2, ",", "."),
            'Menge' => number_format($mengehl, 2, ",", "."),
            'Betrag' => number_format($betrag, 2, ",", "."),
        ]);
                    } else {
                        $content .= $this->formatString('
            <element id="steuerklasse{n}">{plato}</element>
            <element id="steuersatz{n}">{steuersatz}</element>
            <element id="steuerbetrag{nhack}">{steuerbetrag}</element>
            <element id="versteuerung{n}">{versteuerung}</element>
            <element id="betrag{n}">{betrag}</element>', [
                'n' => $n,
                'nhack' => $n <= 1 ? $n : $n + 20,
                'plato' => $swfloor,
                'steuersatz' => number_format($steuersatz, 4, ".", ","),
                'steuerbetrag' => number_format($litersatz, 2, ".", ","),
                'versteuerung' => number_format($mengehl, 2, ".", ","),
                'betrag' => number_format($betrag, 2, ".", ","),
            ]);
                    }
                }
	        } else {
                $status = ($Sud["BierWurdeGebraut"]) ? (($Sud["BierWurdeVerbraucht"]) ? STATUS_EMPTIED : (($Sud["BierWurdeAbgefuellt"]) ? (       (date_add(new DateTime($this->renderDate($this->localToUtc($Sud["Abfuelldatum"]))), new DateInterval('P' . 7 * $Sud["Reifezeit"] . 'D'))->format("Y-m-d") < date("o-m-d"))       ? STATUS_COMPLETE : STATUS_CONDITIONING) : STATUS_FERMENTATION)) : ($this->localToUtc($Sud["Braudatum"] == date("o-m-d") ? STATUS_BREWDAY : STATUS_PREPARING));
                $status = $this->statusWord($status);
		        $content .= $this->formatString('
        <tr>
          <td><a href="{href}">{Sudname}</a></td>
          <td>{Status}</td>
          <td>{Braudatum}</td>
        </tr>', [
			'href' => $href,
			'Sudname' => $Sud["Sudname"],
			'Status' => $status,
			'Braudatum' => $Sud["Braudatum"]
        ]);
            }
        }
        if ($mode == "steuer") {
            $content .= $this->formatString('
        <tr>
          <td colspan="5">Zu entrichtende Steuer</td>
          <td>{Summe}</td>
        </tr>
        <tr>
          <td colspan="6">Diese Daten können als <a href="{url}">XML-Datei</a> heruntergeladen werden, um sie in ein <a href="https://www.formulare-bfinv.de/ffw/action/invoke.do?id=2075">Online-Steuerformular 2075</a> hochzuladen und auszudrucken.</td>
        </tr>
      </table>', [
          'Summe' => number_format($gesamtbetrag, 2, ",", "."),
          'url' => "?download_2075=" . $year
      ]);
        } elseif ($mode != "xml") {
            $content .= '
      <tr>
        <td colspan="2" style="font-size:10pt; text-align:center">Rezepte erstellt mit dem <a href="http://www.joerum.de/kleiner-brauhelfer/doku.php">Kleinen Brauhelfer</a><xsl:text> </xsl:text><xsl:value-of select="Version/kleiner-brauhelfer"/>.</td>
      </tr>
    </table>';
        } else {
            $content .= $this->formatString('
            <element id="betrag23">{summe}</element>
            <element id="euroInBuchstaben">{wort}</element>
        </datarow>
    </instance>
</xml-data>', [
    'summe' => number_format($gesamtbetrag, 2, ".", ","),
    'wort' => "--- " . $this->zahlWort(floor($gesamtbetrag)) . " ---"
]);
        }
        $db->close();
        
        return $content;

    }



    function kbh_send_2075($year) {

        $filename = "Formular-2075-" . $year;

        header("Expires: 0");
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");	
        header("Content-type: application/xml");
        header("Content-Disposition:attachment; filename={$filename}");

        echo $this->kbh_process_table("xml", $year);

        exit();
    }
    

    
    function bs_recipe_shortcode($atts) {

    	$a = shortcode_atts (array(
    		'title' => null
        ), $atts);
        
    	$location = get_option('wp_brewing_bs_location', null);
    	$cache = get_option('wp_brewing_bs_cache', 3600);
    	if (strpos($location, '//') !== false) {
            $path = get_temp_dir() . "/wp-brewing-bs-recipes.bsmx";
            if ((!file_exists($path)) || (time()-filemtime($location) > $cache)) {
                $response = wp_remote_get($location);
                if (is_array($response)) {
                    $data = $response['body'];
                    file_put_contents($path, $data);
                    $location = $path;
                } else {
                    return '[embedding recipe failed.] <!-- could not load BeerSmith recipes from ' . $location . ' -->';
                }
            }
    	}

        $doc = new DOMDocument();
        $doc->loadHTMLFile($location);
        $xpath = new DOMXPath($doc);
        $recipes = $xpath->query('//recipe[f_r_name]');
        if ( $recipes->length < 1 ) {
            return null;
        }
        foreach ($recipes as $recipe) {
            $recipe = [
                "name" => $recipe->getElementsByTagName('f_r_name')->item(0)->textContent,
                "brewer" => $recipe->getElementsByTagName('f_r_brewer')->item(0)->textContent,
                "assistant" => $recipe->getElementsByTagName('f_r_asst_brewer')->item(0)->textContent,
                "created_at" => $this->localToUtc($recipe->getElementsByTagName('f_r_inv_date')->item(0)->textContent),
                "updated_at" => $this->localToUtc($recipe->getElementsByTagName('_mod_')->item(0)->textContent),
                "brewed_at" => $this->localToUtc($recipe->getElementsByTagName('f_r_date')->item(0)->textContent),
                "planned_batch_volume" => $this->flOzToL($recipe->getElementsByTagName('f_r_equipment')->item(0)->getElementsByTagName('f_e_batch_vol')->item(0)->textContent),
                "bottled_volume" => $this->flOzToL($recipe->getElementsByTagName('f_r_final_vol_measured')->item(0)->textContent),
                "stammwuerze" => 0, // grad plato
                "restextrakt" => 0, // GG%
                "abv" => 0, // %vol
                "ibu" => 0, // IBU
                "ebc" => 0, // EBC
                "calories" => 0, // kcal/100ml
                "co2" => 0, // CO2 g/l
                "drink_temp" => 0, // °C
                "song" => null, // "Song zum Bier" :-) (free text)
                "stock" => null, // "Restbestand" (free text)
                "containers" => null, // "Gebinde" (free text)
                "pack_color" => null, // "Kronkorkenfarbe" (free text)
                "age_days" => 0
            ];

            $content .= $this->renderRecipe($recipe);
            
        }

        return "-" . $content;
    }



    function bs_recipes_shortcode($atts) {

        $att = shortcode_atts (array(
            'mode' => null,
            'select' => null
        ), $atts);
        if ($att['select']) {
            $where = " WHERE " . $att['select'];
        } else {
            $where = "";
        }

    	$location = get_option('wp_brewing_bs_location', null);
    	$cache = get_option('wp_brewing_bs_cache', 3600);
    	if (strpos($location, '//') !== false) {
            $path = get_temp_dir() . "/wp-brewing-bs-recipes.bsmx";
            if ((!file_exists($path)) || (time()-filemtime($location) > $cache)) {
                $response = wp_remote_get($location);
                if (is_array($response)) {
                    $data = $response['body'];
                    file_put_contents($path, $data);
                    $location = $path;
                } else {
                    return '[embedding recipe failed.] <!-- could not load BeerSmith recipes from ' . $location . ' -->';
                }
            }
    	}


        return "dummy2";

    }



    function bjcp_styleguide_shortcode($atts) {

        $id = $_GET['id'];

        if ($id) {
            return $this->renderStyle($id);
        } else {
            return $this->renderStyleList();
        }

    }
    

    
    function bjcp_style_shortcode($atts) {
        
        $att = shortcode_atts (array(
            'class' => null,
            'category' => null,
            'subcategory' => null,
            'specialty' => null,
            'id' => null
        ), $atts);
        if ($att["class"]) { $param = $att["class"]; }
        elseif ($att["category"]) { $param = $att["category"]; }
        elseif ($att["subcategory"]) { $param = $att["subcategory"]; }
        elseif ($att["specialty"]) { $param = $att["specialty"]; }
        return $this->renderStyle($param);
        
    }
    
}



new WP_Brewing();
