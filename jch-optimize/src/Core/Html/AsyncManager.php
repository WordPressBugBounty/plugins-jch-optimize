<?php

/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2022 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

namespace JchOptimize\Core\Html;

use JchOptimize\Core\FeatureHelpers\DynamicJs;
use Joomla\Registry\Registry;

use function array_map;
use function defined;
use function html_entity_decode;
use function implode;
use function is_array;
use function json_encode;

defined('_JCH_EXEC') or die('Restricted access');

class AsyncManager
{
    /**
     * @var string
     */
    protected string $onUserInteractFunction = '';
    /**
     * @var string
     */
    protected string $onDomContentLoadedFunction = '';
    /**
     * @var string
     */
    protected string $loadCssOnUIFunction = '';
    /**
     * @var string
     */
    protected string $loadScriptOnUIFunction = '';
    /**
     * @var string
     */
    protected string $loadReduceDomFunction = '';
    /**
     * @var Registry
     */
    private Registry $params;

    /**
     * @param   Registry  $params
     */
    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    public function loadCssAsync($cssUrls): void
    {
        $this->loadOnUIFunction();

        $sNoScriptUrls = implode(
            "\n",
            array_map(function ($url) {
                //language=HTML
                return '<link rel="stylesheet" href="' . $url . '" />';
            }, $cssUrls)
        );

        $aJsonEncodedUrlArray = $this->jsonEncodeUrlArray($cssUrls);

        $this->loadCssOnUIFunction = <<<HTML
<script>
let jch_css_loaded = false;

onUserInteract(function(){ 
	const css_urls = {$aJsonEncodedUrlArray};
        
	if (!jch_css_loaded){
	    	css_urls.forEach(function(url, index){
	       		let l = document.createElement('link');
			l.rel = 'stylesheet';
			l.href = url;
			let h = document.getElementsByTagName('head')[0];
			h.append(l); 
	    	});
	    
		jch_css_loaded = true;
        document.dispatchEvent(new Event("onJchCssAsyncLoaded"));
    }
});
</script>
<noscript>
{$sNoScriptUrls}
</noscript>
HTML;
    }

    private function loadOnUIFunction(): void
    {
        $this->onUserInteractFunction = <<<HTML
<script>
function onUserInteract(callback) { 
	window.addEventListener('load', function() {
	        if (window.pageYOffset !== 0){
	        	callback();
	        }
	});
	
     	const events = ['keydown', 'keyup', 'keypress', 'input', 'auxclick', 'click', 'dblclick', 
     	'mousedown', 'mouseup', 'mouseover', 'mousemove', 'mouseout', 'mouseenter', 'mouseleave', 'mousewheel', 'wheel', 'contextmenu',
     	'pointerover', 'pointerout', 'pointerenter', 'pointerleave', 'pointerdown', 'pointerup', 'pointermove', 'pointercancel', 'gotpointercapture',
     	'lostpointercapture', 'pointerrawupdate', 'touchstart', 'touchmove', 'touchend', 'touchcancel'];
         
	document.addEventListener('DOMContentLoaded', function() {
    	events.forEach(function(e){
			window.addEventListener(e, function() {
	        		callback();
			}, {once: true, passive: true});
    	});
	});
}
</script>
HTML;
    }

    /**
     * @return false|string
     */
    private function jsonEncodeUrlArray($aUrls)
    {
        $aHtmlDecodedUrls = array_map(function ($mUrl) {
            if (is_array($mUrl)) {
                if (!empty($mUrl['url'])) {
                    $mUrl['url'] = html_entity_decode($mUrl['url']);
                }

                return $mUrl;
            }

            return html_entity_decode($mUrl);
        }, $aUrls);

        return json_encode($aHtmlDecodedUrls);
    }

    public function printHeaderScript(): string
    {
        $this->loadJsDynamic(DynamicJs::$aJsDynamicUrls);
        $this->loadReduceDom();

        return $this->onUserInteractFunction . "\n" .
               $this->onDomContentLoadedFunction . "\n" .
               $this->loadCssOnUIFunction . "\n" .
               $this->loadScriptOnUIFunction . "\n" .
               $this->loadReduceDomFunction;
    }

    public function loadJsDynamic($jsUrls): void
    {
        if ($this->params->get('pro_reduce_unused_js_enable', '0') &&
            !empty($jsUrls)) {
            $this->loadOnUIFunction();

            $aJsonEncodedUrlArray = $this->jsonEncodeUrlArray($jsUrls);

            $this->loadScriptOnUIFunction = <<<HTML
<script>
let jch_js_loaded = false;

const jchOptimizeDynamicScriptLoader = {
	queue: [], // Scripts queued to be loaded synchronously
	loadJs: function(js_obj) {
        
		let scriptNode = document.createElement('script');
       
		if ('noModule' in HTMLScriptElement.prototype && js_obj.nomodule){
			this.next();
            		return;
		}
		
		if (!'noModule' in HTMLScriptElement.prototype && js_obj.module){
			this.next();
            		return;
		}
        
        	if(js_obj.module){
                	scriptNode.type = 'module';
                	scriptNode.onload = function(){
                            	jchOptimizeDynamicScriptLoader.next();
                	}
        	}
   
		if (js_obj.nomodule){
			scriptNode.setAttribute('nomodule', '');
		}
        
		if(js_obj.url) { 
            		scriptNode.src = js_obj.url;
        	}
        	
        	if(js_obj.content)
                {
                     	scriptNode.text = js_obj.content;
                }
		document.head.appendChild(scriptNode);
	},
	add: function(data) {
		// Load an array of scripts
		this.queue = data;
		this.next();
	},
	next: function() {
		if(this.queue.length >= 1) {
			// Load the script
			this.loadJs(this.queue.shift());
		}else{
			return false;
		}
	}
};

onUserInteract( function(){
    
   	let js_urls = $aJsonEncodedUrlArray 
   	    	
   	if (!jch_js_loaded){
   	    	jchOptimizeDynamicScriptLoader.add(js_urls);
   	    	jch_js_loaded = true;
            document.dispatchEvent(new Event("onJchJsDynamicLoaded"));
   	}
});
</script>
HTML;
        }
    }

    public function loadReduceDom(): void
    {
        if ($this->params->get('pro_reduce_dom', '0')) {
            $this->loadOnUIFunction();

            $this->loadReduceDomFunction = <<<HTML
<script>
let jch_dom_loaded = false;

onUserInteract(function(){
    if(!jch_dom_loaded) {
	    const containers = document.getElementsByClassName('jch-reduced-dom-container');
	
	    Array.from(containers).forEach(function(container){
       		//First child should be templates with content attribute
		    let template  = container.firstChild; 
		    //clone template
		    let clone = template.content.firstElementChild.cloneNode(true);
		    //replace container with content
		    container.parentNode.replaceChild(clone, container); 
	    })
	
	    jch_dom_loaded = true;
        document.dispatchEvent(new Event("onJchDomLoaded"));
	}
});
</script>
HTML;
        }
    }

    private function loadOnDomContentLoadedFunction(): void
    {
        $this->onDomContentLoadedFunction = <<<HTML
<script>
function onDomContentLoaded(callback) {
	document.addEventListener('DOMContentLoaded', function(){
		callback();
	})
}
</script>
HTML;
    }
}
