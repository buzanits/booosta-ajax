<?php
namespace booosta\ajax;

use \booosta\Framework as b;
b::init_module('ajax');

class Ajax extends \booosta\base\Module
{
  use moduletrait_ajax;

  protected $names;
  protected $declarations;
  protected $initcode;
  protected $requestcode;
  protected $responsecode;
  protected $response_error_action;
  protected $result_tag;
  protected $url;

  // $names is an array holding the names of the Requesters or a string with the name of the only requester
  // $result_tags is an array holding the names of the XML result tags indexed by the Requester names
  //   if it holds a double indexed array then several result tags are returned

  // possible calls:
  // new Ajax('myname');  # get result in ajax_result['myname']
  // new Ajax('myname', 'myresult);  # get result in ajax_result['myresult']
  // new Ajax('myname', array('res1', 'res2'));  # get results in ajax_result['myname']['res1'] and ajax_result['myname']['res2']
  // new Ajax(array('name1', 'name2'), 'myresult');  # get results in ajax_result['name1']['myresult'] and ajax_result['name2']['myresult']
  // new Ajax(array('name1', 'name2'), array('name1'=>'res1', 'name2'=>'res2'));  # get results in ajax_result['res1'] and ajax_result['res2']
  // new Ajax(array('name1', 'name2'),  array('res1', 'res2'));  # get results in ajax_result[$name]['res1'] and ajax_result[$name]['res2']
  // new Ajax(array('name1', 'name2'), array('name1'=>array('res1', 'res2'), 'name2'=>array('res3', 'res4')))  # get results in ajax_result[$name][$res]

  public function __construct($names = 'default', $result_tags = 'result')
  {
    parent::__construct();

    if(is_string($names)) $names = [$names];
    $this->names = $names;

    $this->result_tag = [];
    if($result_tags === null) $result_tags = [];

    foreach($names as $name):
      if(is_string($result_tags)) $this->result_tag[$name] = $result_tags;
      elseif(isset($result_tags[$name])) $this->result_tag[$name] = $result_tags[$name];
      else $this->result_tag[$name] = $result_tags;

      if($this->result_tag[$name] == ''):
        if($result_tags['default_']):
          $this->result_tag[$name] = $result_tags['default_'];
        else:
          $this->result_tag[$name] = 'result';
        endif;
      endif;
    endforeach;

    $this->requestcode = [];
    $this->responsecode = [];
    $this->response_error_action = [];
  }

  public function set_declarations($code) { $this->declarations = $code; }
  public function add_declarations($code) { $this->declarations .= $code; }
  public function set_initcode($code) { $this->initcode = $code; }
  public function add_initcode($code) { $this->initcode .= $code; }

  // set_url(str);  # sets url for all requesters
  // set_url(str url, str name);  # sets url for requester name
  // set_url(indexed array); # sets url for every requester - array indexed with names
  public function set_url($url, $name = null) 
  { 
    if($name === null):
      $this->url = $url;
    else:
      if(!is_array($this->url)) $this->url = [];
      $this->url[$name] = $url;
    endif;
  }

  public function set_requestcode($name, $code = null)
  {
    if($code == null):
      $code = $name;
      $name = 'default';
    endif;

    $this->requestcode[$name] = $code;
  }

  public function add_requestcode($name, $code = null)
  {
    if($code == null):
      $code = $name;
      $name = 'default';
    endif;

    $this->requestcode[$name] .= $code;
  }

  public function set_responsecode($name, $code = null)
  {
    if($code == null):
      $code = $name;
      $name = 'default';
    endif;

    $this->responsecode[$name] = $code;
  }

  public function add_responsecode($name, $code = null)
  {
    if($code == null):
      $code = $name;
      $name = 'default';
    endif;

    $this->responsecode[$name] .= $code;
  }

  public function set_response_error_action($name, $code = null)
  {
    if($code == null):
      $code = $name;
      $name = 'default';
    endif;

    $this->response_error_action[$name] = $code;
  }

  public function add_response_error_action($name, $code = null)
  {
    if($code == null):
      $code = $name;
      $name = 'default';
    endif;

    $this->response_error_action[$name] .= $code;
   }


  public function set_result_tag($tag) { $this->result_tag = $tag; }

  public function get_response($tag, $results)
  {
    if(is_array($results)) return \booosta\array2xml($results, $tag);

    if($tag === null):
      $open = ''; 
      $close = '';
    else:
      $open = "<$tag>";
      $close = "</$tag>";
    endif;

    #$result = '<?xml version="1.0" ?'.'>';
    $result = '<root>';
    $result .= "$open$results$close";
    $result .=  '</root>';

    #\booosta\debug($result);
    return $result;
  }

  public function print_response($tag, $results) 
  { 
    header('Content-type: text/xml');
    print self::get_response($tag, $results);
  }

  public function print_javascript() { print $this->get_javascript(); }
  public function get_js() { return $this->get_javascript(); }
  public function print_js() { $this->print_javascript(); }

  public function get_javascript($scripttags = false)
  {
    if($scripttags) $ret = "
<script type='text/javascript'>
    ";

    $ret .= "
$this->declarations
";
    if($this->initcode) $ret .= "window.onload = init_ajax;\n";

foreach($this->names as $name):
  $ret .= "var requester_$name = null;\n";
  $ret .= "var url_$name;\n";
  $ret .= "var ajax_result = Array();\n";
endforeach;

  if($this->initcode) $ret .= "
function init_ajax()
{
  $this->initcode
  return true;
}
";

foreach($this->names as $name):
  if($this->requestcode[$name] == '') 
    $this->requestcode[$name] = "if(typeof request_{$name}_ == 'function') request_{$name}_(arg1, arg2, arg3, arg4, arg5);";

  if(is_string($this->url)) $url = $this->url;
  elseif(is_array($this->url) && isset($this->url[$name])) $url = $this->url[$name];
  else $url = '';

  $ret .= "
var cancel_request_$name = false;
function request_$name(arg1, arg2, arg3, arg4, arg5)
{
  url_$name = '$url';

  " . $this->requestcode[$name] . "

  if(cancel_request_$name) { cancel_request_$name = false; return false; }

  if(requester_$name != null && requester_$name.readyState != 0 && requester_$name.readyState != 4)
    { requester_$name.abort(); }

  try{ requester_$name = new XMLHttpRequest(); }
  catch(error) { try{ requester_$name = new ActiveXObject(\"Microsoft.XMLHTTP\"); }
                 catch(error) { requester_$name = null; return false; }
               }

  requester_$name.onreadystatechange = function() { response_$name(arg1, arg2, arg3, arg4, arg5); };
  requester_$name.open(\"GET\", url_$name);
  requester_$name.setRequestHeader(\"If-Modified-Since\", \"Sat, 1 Jan 2000 00:00:00 GMT\");
  requester_$name.send(null);
}

function response_$name(arg1, arg2, arg3, arg4, arg5)
{
  if(requester_$name.readyState == 4) {
    try { 
      if(requester_$name.status == 200) {
        var nodes;
";

  if(!is_array($this->result_tag[$name])):
    $ret .= "
        ajax_result['$name'] = '';
        nodes = requester_$name.responseXML.getElementsByTagName(\"".$this->result_tag[$name]."\")[0].childNodes;
        for(var i=0; i<nodes.length; i++) { ajax_result['$name'] += nodes[i].nodeValue; }
";
  else:
    $ret .= "        ajax_result['$name'] = new Object(); \n";
    foreach($this->result_tag[$name] as $tagname):
      $ret .= "
        ajax_result['$name']['$tagname'] = '';
        nodes = requester_$name.responseXML.getElementsByTagName(\"$tagname\")[0].childNodes;
        for(var i=0; i<nodes.length; i++) { ajax_result['$name']['$tagname'] += nodes[i].nodeValue; }
";
    endforeach;
  endif;

  if($this->responsecode[$name] == '') $this->responsecode[$name] = "\n        response_{$name}_(arg1, arg2, arg3, arg4, arg5);";

  $ret .= $this->responsecode[$name]."
         } else if(requester_$name.status != 0) {
           ".$this->response_error_action[$name]."
         }
    }
    catch(error) { }
  }
}

";
endforeach;

if($scripttags) $ret .= '
</script>
';

    return $ret;
  }
}
