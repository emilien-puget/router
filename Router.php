<?php



namespace ep\router;

require "router.config.php";

class Router
{
    private static $instance = false;
    private $map;

    private function __construct()
    {
        $this->map = RouterSax::getInstance('routes.xml', _ROUTER_DEFAULT_LANG_, _ROUTER_LANG_MOD_)->getMap();
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = New Router();
        }
        return self::$instance;

    }

    /**
     * Return the url of the route or false if not found
     *
     * @param        $name
     * @param array  $params
     * @param string $iso_lang
     *
     * @return bool|string
     * @throws Exception
     */
    public function getRoute($name, $params = array(), $iso_lang = _ROUTER_DEFAULT_LANG_)
    {
        foreach ($this->map as $route) {
            $url_exploded = explode('/', $route['original_url']);
            if ($route['name'] == $name && $route['params']['_lang'] == $iso_lang && (_ROUTER_LANG_MOD_ == 1 && $url_exploded[1] != $iso_lang)) {
                unset($params['_lang']);
                foreach ($params as $key => $param) {
                    $route['original_url'] = str_replace('{:'.$key.'}', $param, $route['original_url']);
                }
                $matches = array();
                $ret = preg_match_all('#(?P<parameter>{:+[a-zA-Z_0-9]+})#', $route['original_url'], $matches);

                if ($ret) {
                    Throw New Exception(sprintf(
                        'Parameter %1s missing for route:%2s',
                        implode($matches['parameter'], ', '),
                        $name
                    ));
                }
                return $route['original_url'];
            }
        }
        return false;
    }

    /**
     * Match the current url with the map of routes. Return an array of informations about the routes or false if no routes are found
     *
     * @return array|bool
     */
    public function matchCurrentUrl()
    {
        $url = preg_replace('#^'._ROUTER_SUB_DIR_.'#', '', strtok($_SERVER['REQUEST_URI'], '?'));

        foreach ($this->map as $key => $routes) {
            $matches = array();
            $ret = preg_match_all('#^'.$key.'$#', $url, $matches);
            if ($ret == 1 && (
                    !$routes['method'] ||
                    $routes['method'] &&
                    mb_strtoupper($routes['method']) == $_SERVER['REQUEST_METHOD']
                )
            ) {
                $params = $routes['params'];
                foreach ($params as $k => $param) {
                    if (isset($matches[$k])) {
                        if (empty($matches[$k][0]))
                            $params[$k] = null;
                        else
                            $params[$k] = $matches[$k][0];
                    }
                }
                return array('name' => $routes['name'], 'action' => $routes['action'], 'params' => $params);
            }
        }
        return false;
    }
}