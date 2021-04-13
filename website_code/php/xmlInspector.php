<?php
/**
 * Licensed to The Apereo Foundation under one or more contributor license
 * agreements. See the NOTICE file distributed with this work for
 * additional information regarding copyright ownership.

 * The Apereo Foundation licenses this file to you under the Apache License,
 * Version 2.0 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.

 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class XerteXMLInspector
{

    private $fname;
    private $xml;
    private $name;
    private $models;
    private $mediaIsUsed;
    private $language;
    private $theme;
    private $glossary;
    private $resultpageEnabled;
    private $hasResultPage;
    private $pages;

    private function addModel($model)
    {
        foreach ($this->models as $presentmodel) {
            if ($presentmodel == $model) {
                return;
            }
        }
        array_push($this->models, $model);
    }

    private function addPage($node, $i)
    {
        $page = new stdClass();
        $name = (string)$node['name'];
        // This may contain HTML tags, convert to plain text
        // Remove the HTML tags
        $name = strip_tags($name);
        // Make sure it all fits on one line
        $name = str_replace("\n", "", $name);
        // encode
        $name = base64_encode($name);
        $page->name = $name;
        $page->type = $node->getName();
        $page->index = $i;

        array_push($this->pages, $page);
    }

    private function fixXmlFile($name)
    {
        $xmlcontents = file_get_contents($name);

        //A. Issue 1: previously unescaped '&'
        //_debug("1. " . $xmlcontents);
        // Ok replace known escape sequences to something without an '&'
        $xmlcontents = str_replace('&lt;', '%%%lt;', $xmlcontents);
        $xmlcontents = str_replace('&gt;', '%%%gt;', $xmlcontents);
        $xmlcontents = str_replace('&quot;', '%%%quot;', $xmlcontents);
        $xmlcontents = str_replace('&nbsp;', '%%%nbsp;', $xmlcontents);
        $xmlcontents = str_replace('&amp;', '%%%amp;', $xmlcontents);

        //_debug("2. " . $xmlcontents);
        // replace & with &amp;
        $xmlcontents = str_replace('&', '&amp;', $xmlcontents);

        // replace known escape sequences back
        $xmlcontents = str_replace('%%%lt;', '&lt;', $xmlcontents);
        $xmlcontents = str_replace('%%%gt;', '&gt;', $xmlcontents);
        $xmlcontents = str_replace('%%%quot;', '&quot;', $xmlcontents);
        $xmlcontents = str_replace('%%%nbsp;', '&nbsp;', $xmlcontents);
        $xmlcontents = str_replace('%%%amp;', '&amp;', $xmlcontents);

        //_debug("3. ". $xmlcontents);
        if ($this->isValidXml($xmlcontents)) {
            _debug("We were able to fixup the file : $name");
            $res = file_put_contents($name, $xmlcontents);
            return true;
        }

        //B. Issue 2: Spurious Alt-B characters (still NO idea where they come from.
        $xmlcontents = str_replace(chr(11), ' ', $xmlcontents);
        //_debug("4. ". $xmlcontents);
        if ($this->isValidXml($xmlcontents)) {
            _debug("We were able to fixup the file : $name");
            $res = file_put_contents($name, $xmlcontents);
            return true;
        }

        return false;
        //_debug("5. " . $res);
    }

    /**
     * Checks to see if some XML is valid
     * @param string $string as a string.
     * @return boolean true if the XML appears valid.
     */
    private function isValidXml($string)
    {

        $orig_error_setting = libxml_use_internal_errors(true);
        // See security note elsewhere in this file and http://php.net/manual/en/function.libxml-disable-entity-loader.php
        // Supported from 5.2.11, so allow for older versions to work as well.
        if (PHP_VERSION < '8' && function_exists('libxml_disable_entity_loader'))
        {
            $original_el_setting = libxml_disable_entity_loader(false);
        }

        // Suppress anything PHP might moan about.
        $temp = @simplexml_load_string($string);
        $ok = false;
        if (!$temp) {
            $errors = array();
            foreach (libxml_get_errors() as $libXMLError) {
                $errors[] = $libXMLError->file . ' : line ' . $libXMLError->line . ', col:' . $libXMLError->column . ', message:' . $libXMLError->message;
            }
            libxml_clear_errors();
            _debug("Error detected in XML : " . implode(',', $errors));
            $ok = false;
        } else {
            $ok = true;
        }
        if (PHP_VERSION < '8' && function_exists('libxml_disable_entity_loader'))
        {
            libxml_disable_entity_loader($original_el_setting);
        }
        libxml_use_internal_errors($orig_error_setting);
        return $ok;
    }

    public function loadTemplateXML($name)
    {
        _debug("Trying to simplexml_load_file : $name");
        $this->fname = $name;

        $this->models = array();
        $this->pages = array();
        // We don't really want to load external entities into our XML; but have no choice here. Make sure it's enabled (revert it later on).
        // This can be a security issue - see .e.g http://php.net/manual/en/function.libxml-disable-entity-loader.php
        // Supported from 5.2.11, so allow for older versions to work as well.

        $orig_error_setting = libxml_use_internal_errors(true);
        if (PHP_VERSION < '8' && function_exists('libxml_disable_entity_loader'))
        {
            $original_el_setting = libxml_disable_entity_loader(false);
        }

        if (!file_exists($name)) {
            _debug("Can't load : $name - it's not there!");
            return false;
        }

        $xml = file_get_contents($name);
        if (!$this->isValidXml($xml)) {
            // Try and  fix it ?
            _debug("Invalid XML found; trying to repair");
            $ok = $this->fixXmlFile($name);
            if ($ok === false) {
                // Could not fix it!
                _debug("Could not fix up the xml file with $name; consult error logs etc.");
                return false;
            }
            else {
                // reload.
                $xml = file_get_contents($name);
            }
        }

        $this->xml = simplexml_load_string($xml);
        if (strlen((string)$this->xml['glossary'])>0)
        {
            $this->glossary = true;
        }
        else
        {
            $this->glossary = false;
        }
        $this->language = (string)$this->xml['language'];
        if (strlen($this->language) == 0)
            $this->language = 'en-GB';
        $name = (string)$this->xml['name'];
        // This may contain HTML tags, convert to plain text
        // Remove the HTML tags
        $name = strip_tags($name);
        // Convert HTML entities to single characters
        $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        $this->name = $name;

        $this->models = array();

        //Gets the value of resultpage (true/false)
        $rPath = $this->xml->xpath('/*');
        $rElmnt = $rPath[0];
        $this->resultpageEnabled = (string)($rElmnt['resultpage']);
        $this->hasResultPage = ($rElmnt->children());
        
        $nodes = $this->xml->xpath('/*/*');

        $i = 0;
        foreach ($nodes as $node) {
            $this->addModel($node->getName());
            $this->addPage($node, $i);
            $i++;
        }
        $this->mediaIsUsed = false;
        $str = (string)$this->xml['media'];
        if (strlen($str) > 0) {
            $this->mediaIsUsed = true;
        }
        if (strlen((string)$this->xml['theme'])>0)
        {
            $this->theme=(string)$this->xml['theme'];
        }
        else
        {
            $this->theme = "default";
        }
        if (PHP_VERSION < '8' && function_exists('libxml_disable_entity_loader'))
        {
            libxml_disable_entity_loader($original_el_setting);
        }
        libxml_use_internal_errors($orig_error_setting);
    }

    public function getUsedModels()
    {
        return $this->models;
    }

    public function mediaIsUsed()
    {
        return $this->mediaIsUsed;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function getName()
    {
        return $this->name;
    }

    public function modelUsed($model)
    {
        foreach ($this->models as $presentmodel) {
            if ($presentmodel == $model) {
                return true;
            }
        }
        return false;
    }

    public function glossaryUsed()
    {
        return $this->glossary;
    }

    public function getPages()
    {
        return $this->pages;
    }
}

//$template = new XerteXMLInspector();
//$template->loadTemplateXML('template.xml');
//$models = $template->getUsedModels();
//foreach($models as $model)
//{
//	print($model . "\n");
//}

?>