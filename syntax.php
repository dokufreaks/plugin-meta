<?php
/**
 * Meta Plugin: Sets metadata for the current page
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_meta extends DokuWiki_Syntax_Plugin {
    function getType() { return 'substition'; }
    function getSort() { return 99; }
    function connectTo($mode) { $this->Lexer->addSpecialPattern('~~META:.*?~~',$mode,'plugin_meta');}

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler){
        $match = substr($match,7,-2); //strip ~~META: from start and ~~ from end

        $data = array();
        $pairs = explode('&', $match);
        foreach ($pairs as $pair) {
            list($key, $value) = explode('=', $pair, 2);
            list($key, $subkey) = explode(' ', $key, 2);
            if (trim($subkey)) $data[trim($key)][trim($subkey)] = trim($value);
            else $data[trim($key)] = trim($value);
        }
        $data = array_change_key_case($data, CASE_LOWER);

        return $data;
    }

    /**
     * Create output
     */
    function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode == 'xthml') {
            return true; // don't output anything
        } elseif ($mode == 'metadata') {
            /** @var Doku_Renderer_metadata $renderer */

            // do some validation / conversion for date metadata
            if (isset($data['date'])) {
                if (is_array($data['date'])) {
                    foreach ($data['date'] as $key => $date) {
                        $date = $this->_convertDate(trim($date));
                        if (!$date) unset($data['date'][$key]);
                        else $data['date'][$key] = $date;
                    }
                } else {
                    unset($data['date']);
                }
            }

            // now merge the arrays
            $protected = array('description', 'date', 'contributor');
            foreach ($data as $key => $value) {

                // be careful with sub-arrays of $meta['relation']
                if ($key == 'relation') {
                    foreach ($value as $subkey => $subvalue) {
                        if ($subkey == 'media') {
                            $renderer->meta[$key][$subkey][cleanID($subvalue)] = @file_exists(mediaFN($subvalue));
                        } elseif ($subkey == 'firstimage') {
                            /* The metadata renderer overrides the first image value with its internal value at the end.
                            Therefore the only thing we can do is setting this internal value by calling _firstimage.
                            This fails if there has already been a first image saved. */
                            $renderer->_firstimage($subvalue);
                        } else { // for everything else assume that we have a page id
                            $renderer->meta[$key][$subkey][cleanID($subvalue)] = page_exists($subvalue);
                        }
                    }

                    // be careful with some senisitive arrays of $meta
                } elseif (in_array($key, $protected)) {
                    if (array_key_exists($key, $renderer->meta)) {
                        $renderer->meta[$key] = array_merge($renderer->meta[$key], $value);
                    } else {
                        $renderer->meta[$key] = $value;
                    }

                    // no special treatment for the rest
                } else {
                    $renderer->meta[$key] = $value;
                }
            }
        }
    }

    /**
     * converts YYYY-MM-DD[ hh:mm:ss][ -> [YYYY-MM-DD ]hh:mm:ss] to PHP timestamps
     */
    function _convertDate($date) {
        list($start, $end) = explode('->', $date, 2);

        // single date
        if (!$end) {
            list($date, $time) = explode(' ', trim($start), 2);
            if (!preg_match('/\d{4}\-\d{2}\-\d{2}/', $date)) return false;
            $time = $this->_autocompleteTime($time);
            return strtotime($date.' '.$time);

            // duration
        } else {

            // start
            list($startdate, $starttime) = explode(' ', trim($start), 2);
            $startdate = $this->_autocompleteDate($startdate);
            if (!$startdate) return false;
            $starttime = $this->_autocompleteTime($starttime);

            // end
            list($enddate, $endtime) = explode(' ', trim($end), 2);
            if (!trim($endtime)) { // only time given
                $end_date = $this->_autocompleteDate($enddate, true);
                if (!$end_date) {
                    $endtime = $this->_autocompleteTime($enddate, true);
                    $enddate = $startdate;
                } else {            // only date given
                    $enddate = $end_date;
                    $endtime = '23:59:59';
                }
            } else {
                $enddate = $this->_autocompleteDate($enddate, true);
                if (!$enddate) $enddate = $startdate;
                $endtime = $this->_autocompleteTime($endtime, true);
            }

            $start = strtotime($startdate.' '.$starttime);
            $end   = strtotime($enddate.' '.$endtime);
            if (!$start || !$end) return false;
            return array('start' => $start, 'end' => $end);
        }
    }

    function _autocompleteDate($date, $end=false) {
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $date)) {
            if (preg_match('/^\d{4}\-\d{2}$/', $date))
                // we don't know which month
                return ($end) ? $date.'-28' : $date.'-01';
            elseif (preg_match('/^\d{4}$/', $date))
                return ($end) ? $date.'-12-31' : $date.'-01-01';
            else return false;
        } else {
            return $date;
        }
    }

    function _autocompleteTime($time, $end=false) {
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            if (preg_match('/^\d{2}:\d{2}$/', $time))
                return ($end) ? $time.':59' : $time.':00';
            elseif (preg_match('/^\d{2}$/', $time))
                return ($end) ? $time.':59:59': $time.':00:00';
            else return ($end) ? '23:59:59' : '00:00:00';
        } else {
            return $time;
        }
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
