<?php

namespace ep\router;

class RouterSax
{

    protected static $instance = array();
    protected $map = array();
    protected $default_lang;
    protected $mod;
    protected $route;
    protected $url = array();
    protected $action;
    protected $name;
    protected $method;
    protected $params = array();
    protected $param;
    protected $file;

    private function __construct($file_name, $default_lang, $mod)
    {
        $this->file = dirname(__FILE__).'\routes_'.$file_name.'.php';
        if (_ROUTER_CACHE_ && file_exists($this->file)) {
            require($this->file);
            $this->map = $route_map;
        } else {
            $this->default_lang = $default_lang;
            $this->mod = $mod;

            $sax_parser = xml_parser_create();

            xml_set_object($sax_parser, $this);
            xml_set_element_handler($sax_parser, 'saxStart', 'saxEnd');

            xml_parser_set_option($sax_parser, XML_OPTION_CASE_FOLDING, false);
            xml_parser_set_option($sax_parser, XML_OPTION_SKIP_WHITE, true);
            xml_parse($sax_parser, file_get_contents(dirname(__FILE__).'\\'.$file_name), true);
            xml_parser_free($sax_parser);
        }
    }

    /**
     * Return an instance of a router sax parser for $file_name
     *
     * @param $file_name
     * @param $default_lang
     * @param $mod
     *
     * @return RouterSax
     */
    public static function getInstance($file_name, $default_lang, $mod)
    {
        if (!isset(self::$instance[$file_name])) {
            self::$instance[$file_name] = New RouterSax($file_name, $default_lang, $mod);
        }
        return self::$instance[$file_name];
    }

    /**
     * Called for 'route' and 'url' tags
     *
     * @param $sax
     * @param $data
     *
     * @return bool
     */
    protected function characterData($sax, $data)
    {
        $data = preg_replace("/\r\n|\r|\n|\s+/", '', $data);
        if ($data == '') {
            return false;
        }
        if (count($this->url)) {
            foreach ($this->url as &$url) {
                $url .= $data;
            }
        } else {
//                $this->url = array($data);

            $this->params['_lang'] = _ROUTER_DEFAULT_LANG_;
            $this->route[$data] = array(
                'action' => $this->action,
                'name' => $this->name,
                'params' => $this->params
            );
        }
        return true;
    }

    /**
     * Called  for 'param' tag
     *
     * @param $sax
     * @param $data
     *
     * @return bool
     */
    protected function characterDataParam($sax, $data)
    {
        $data = preg_replace("/\r\n|\r|\n|\s+/", '', $data);
        if ($data == '') {
            return false;
        }
        $this->param['value'] = $data;
        return true;
    }

    /**
     * Called at the start of a tag
     *
     * @param $sax
     * @param $tag
     * @param $attr
     */
    protected function saxStart($sax, $tag, $attr)
    {
        if ($tag == 'route') {
            xml_set_character_data_handler($sax, "characterData");
            $this->action = $attr['action'];
            $this->name = $attr['name'];
            $this->method = (isset($attr['method']) ? $attr['method'] : false);
        } elseif ($tag == 'url') {
            xml_set_character_data_handler($sax, "characterData");
            if (!isset($attr['iso'])) {
                if ($this->mod != 3) {
                    $this->url = array('(/+(?P<_lang>[\w+]{2}))?');
                    $this->params['_lang'] = _ROUTER_DEFAULT_LANG_;
                } else {
                    $this->url = array('');
                }
            } else {
                switch ($this->mod) {
                    case 0:
                        $this->url = array('/'.$attr['iso']);
                        break;
                    case 1:
                        if ($this->default_lang == $attr['iso']) {
                            $this->url = array('', '/'.$attr['iso']);
                        } else {
                            $this->url = array('/'.$attr['iso']);
                        }
                        break;
                }
                $this->params['_lang'] = $attr['iso'];
            }
        } elseif ($tag == 'param') {
            $this->param['name'] = $attr['name'];
            xml_set_character_data_handler($sax, "characterDataParam");
        }
    }

    /**
     * Called at the end of a tag
     *
     * @param $sax
     * @param $tag
     */
    protected function saxEnd($sax, $tag)
    {
        if ($tag == 'route') {
            $this->map = array_merge($this->map, $this->route);
            unset($this->route);
            $this->params = array();
        } elseif ($tag == 'url') {
            foreach ($this->url as $url) {
                $original_url = $url;
                foreach ($this->params as $key => $value) {
                    $url = str_replace('{:'.$key.'}', '(?P<'.$key.'>'.$value.')', $url);
                }
                $this->route[$url] = array(
                    'action' => $this->action,
                    'name' => $this->name,
                    'original_url' => $original_url,
                    'params' => $this->params,
                    'method' => $this->method
                );
            }
            $this->url = array();
        } elseif ($tag == 'param') {
            $this->params[$this->param['name']] = $this->param['value'];
        }
    }

    /**
     * Return the routes as a multidimensional array with the regexp url as the key
     *
     * @return array
     */
    public function getMap()
    {

        if (_ROUTER_CACHE_ && !file_exists($this->file)) {
            file_put_contents($this->file, '<?php $route_map = '.var_export($this->map, true).';');
        }

        return $this->map;
    }
}