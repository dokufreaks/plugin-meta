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

  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-04-15',
      'name'   => 'Meta Plugin',
      'desc'   => 'Sets metadata for the current page',
      'url'    => 'http://wiki.splitbrain.org/plugin:meta',
    );
  }

  function getType(){ return 'substition'; }
  function getSort(){ return 99; }
  function connectTo($mode) { $this->Lexer->addSpecialPattern('~~META:.*?~~',$mode,'plugin_meta');}

  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    $match = substr($match,7,-2); //strip ~~META: from start and ~~ from end
    
    $data = array();
    $pairs = explode('&', $match);
    foreach ($pairs as $pair){
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
  function render($mode, &$renderer, $data){
    if ($mode == 'xthml'){
      return true; // don't output anything
    } elseif ($mode == 'metadata'){
      
      // do some validation / conversion for date metadata
      if (isset($data['date'])){
        if (is_array($data['date'])){
          foreach ($data['date'] as $key => $date){
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
      foreach ($data as $key => $value){
        
        // be careful with sub-arrays of $meta['relation']
        if ($key == 'relation'){
          foreach ($value as $subkey => $subvalue){
            $renderer->meta[$key][$subkey] =
              array_merge($renderer->meta[$key][$subkey], $subvalue);
          }
          
        // be careful with some senisitive arrays of $meta
        } elseif (in_array($key, $protected)){
          if (is_array($value)){
            $renderer->meta[$key] =
              array_merge($renderer->meta[$key], $value);
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
  function _convertDate($date){
    list($start, $end) = explode('->', $date, 2);
    
    // single date
    if (!$end){
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
      if (!trim($endtime)){ // only time given
        $end_date = $this->_autocompleteDate($enddate, true);
        if (!$end_date){
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
  
  function _autocompleteDate($date, $end=false){
    if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $date)){
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
  
  function _autocompleteTime($time, $end=false){
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)){
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

//Setup VIM: ex: et ts=4 enc=utf-8 :
