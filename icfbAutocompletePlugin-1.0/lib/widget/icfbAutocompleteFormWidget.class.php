<?php 
/*
 * (c) 2011 Igor Crevar <crewce@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @author     Igor Crevar <crewce@gmail.com>
 */

/**
 * Provides symfony wiget for facebook style autocomplete which can be used in various of situations like sending messages, etc...
 *
 * Example:
 * <code>
 *  <?php $widget = new icfbAutocompletePlugin( array( 'url' => sfContext::getInstance()->getController()->genUrl('your route to action which will retrieve users') ) ); ?>
 * </code>
 *
 * list of all widget options
 * includeJQuery - set this to false if you already included jquery.js in your action/template
 * includeJQueryUI - set this to false if you already included jquery-ui.js  and jquery-ui.css in your action/template
 * isAjaxForm - if is false(by default) all js are loaded immediately. Otherwise you will need to call getGeneratedJS and static method includeJavaScripts manually

* minLength – default is 1. Min length of characters to invoke ajax call
* url – default is friends.php – url which will be called for retrieving list of users – url for your server response.
* title – default is ‘Remove %s’. This is just title for link for removing already selected users. Handy if you have, for example, application in Serbian or other not English language.
* useCache – default is true. Should plugin use caching? This is handy for optimization because same inputs doesnt require more than one xmlHttpRequest(ajax) call
* formName – default is send_message[user]. This will be the name of hidden inputs fields when you submit the form which includes autompleter.
* sendTitles: default is true. This means that you wont just send (after main form submiting) user ids, but also their titles(usernames,emails, etc) after main form submit.
* onChangeFunc: function($obj){} – handler you can override if you want to do something after someone picks user from the list. $obj is main object of fbautomplete plugin(or invisible generated input[type=text] if you like)
* onRemoveFunc: function($obj){ } – handler which will be called after removing user from list of selected.
* onAlreadySelected: function($obj){} – handler which will be called if you try to add user which is already in list of selected.
* maxUsers: – default 0(which means unlimited). Use this if you want to limit number of users.
* onMaxSelectedFunc: function($obj){} – handler which will be called if you try add more then maxUsers users.
* selected: [] – if you want to pass already selected users. For example: you did form submiting for sending message but you forgot to add subject. The array is array of objects in format [ {id: id1, title: 'title1'}, {id: id2, title: 'title2'}, ... ]
* cache: {} – add already cached inputs.
* generateUrl: function(url) – function can be used to generate dynamic retrieve url. Must return string
* focusWhenClickOnParent: true - if you dont want to autofocus input when click on div parent set this to false
* staticRetrieve: false - if you dont want ajax call just define this function(term) and return array or {id, title[,src]}
* highlight: false - set this to true if you want to see highlighted parts of users titles which matches inputed term
 */
class icfbAutocompleteFormWidget extends sfWidgetForm
{
	protected $generatedJS;
	
	protected function configure($options = array(), $attributes = array())
    {
		//main and required option is to specify url
    	$this->addRequiredOption('url');
		
		//widget options
		$this->addOption('includeJQuery', true);
    	$this->addOption('includeJQueryUI', true);
		$this->addOption('isAjaxForm', false);
		
	    //icfbautocomplete js options
    	$this->addOption('minLength', '1');
    	$this->addOption('title', 'Remove %s');
    	$this->addOption('sendTitles', true);
    	$this->addOption('useCache', true);
    	$this->addOption('onChangeFunc', 'function($obj){ }' ); //$obj.css("top", 2);
    	$this->addOption('onRemoveFunc', 'function($obj){ }' ); //if( $obj.parent().find("span").length === 0 ) $obj.css("top", 0);
    	$this->addOption('onAlreadySelected', 'function($obj){}' );
    	$this->addOption('maxUsers', 0 );
    	$this->addOption('onMaxSelectedFunc', 'function($obj){}' );
    	$this->addOption('generateUrl', 'function(url) { return url; }');
		$this->addOption('focusWhenClickOnParent', true);
		$this->addOption('staticRetrieve', false);
		$this->addOption('highlight', false);
    }
	
	public function render($name, $value = null, $attributes = array(), $errors = array())
	{
		$selectedString = '';
		if ( is_array($value)  &&  count($value) > 0  &&  isset($value['id']) )
		{
			$ids = $value['id'];
			$addTitles = false;
			if ( isset($value['title']) )
			{
				$titles = $value['title'];
				$addTitles = true;
			}
			foreach ($ids as $i => $id)
			{
				if ($i > 0) $selectedString .= ', '; 
				$selectedString .= '{ id: '.$id;
				if ( $addTitles  &&  isset($titles[$i]) )
				{
					$selectedString .= ', title: "'.
									   htmlspecialchars( $titles[$i], ENT_QUOTES, 'UTF-8' ).'"';
				}
				$selectedString .= ' }';
			}			
		}
		$selectedString = "[$selectedString]";
				
		$generateId = $this->generateId($name);	
		$generateIdMain = $generateId.'_main';

		//generate javascript which will make input[type=text] to autocomplete textbox
		$this->generateJS($name, $generateId, $selectedString);
		
		$rv = '<div id="'.$generateIdMain.'" class="fbautocomplete-main-div ui-helper-clearfix">';
		$rv .= '<input id="'.$generateId.'" type="text" />';
		$rv .= '</div>';
		if ( !$this->getOption('isAjaxForm') )
		{
			self::includeJavaScripts($this->getOption('includeJQuery'), $this->getOption('includeJQueryUI'));
			$rv .= sprintf("<script type=\"text/javascript\">\n//<![CDATA[\n  $(function(){ \n%s\n }); \n//]]>\n</script>", $this->generatedJS);
		}		
		return $rv;				  
	}
	
	protected function generateJS($name, $id, $selectedString)
	{
		ob_start();
		?>
$('#<?php echo $id;?>').fbautocomplete({
	url: "<?php echo $this->getOption('url');?>",
	title: "<?php echo $this->getOption('title');?>",
	formName: '<?php echo $name;?>',
	sendTitles:  <?php echo $this->getOption('sendTitles') ? 'true' : 'false';?>,
	onMaxSelectedFunc: <?php echo $this->getOption('onMaxSelectedFunc');?>,
	onAlreadySelected: <?php echo $this->getOption('onAlreadySelected');?>,
	onRemoveFunc: <?php echo $this->getOption('onRemoveFunc');?>,
	onChangeFunc: <?php echo $this->getOption('onChangeFunc');?>,		
	maxUsers: <?php echo $this->getOption('maxUsers');?>,
	minLength: <?php echo $this->getOption('minLength');?>,
	useCache: <?php echo $this->getOption('useCache') ? 'true' : 'false';?>,
	selected: <?php echo $selectedString;?>,
	generateUrl: <?php echo $this->getOption('generateUrl');?>,
	focusWhenClickOnParent: <?php echo $this->getOption('focusWhenClickOnParent');?>,
	staticRetrieve: <?php echo $this->getOption('staticRetrieve') ? 'true' : 'false';?>,
	highlight: <?php echo $this->getOption('highlight')? 'true' : 'false';?>,		
});
<?php 
		$this->generatedJS = ob_get_contents();
		ob_end_clean();		
	}
	
	public function getGeneratedJS()
	{
		return $this->generatedJS;
	}
	
	public static function includeJavaScripts($includeJQuery, $includeJQueryUI)
	{
		$response = sfContext::getInstance()->getResponse();
		if ( $includeJQuery )
		{
			$response->addJavascript( '../icfbAutocompletePlugin/jquery.js' );
		}
		
		if ( $includeJQueryUI )
		{
			$response->addJavascript( '../icfbAutocompletePlugin/jquery-ui/js/jquery-ui-1.8.6.custom.min.js');
			$response->addStylesheet( '../icfbAutocompletePlugin/jquery-ui/css/ui-lightness/jquery-ui-1.8.6.custom.css');
		}
		
		$response->addJavascript( '../icfbAutocompletePlugin/fbautocomplete.js' );
		$response->addStylesheet( '../icfbAutocompletePlugin/fbautocomplete.css' );		
	}
}