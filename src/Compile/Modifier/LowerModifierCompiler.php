<?php
namespace Smarty\Compile\Modifier;
/**
 * Smarty lower modifier plugin
 * Type:     modifier
 * Name:     lower
 * Purpose:  convert string to lowercase
 *
 * @link   https://www.smarty.net/manual/en/language.modifier.lower.php lower (Smarty online manual)
 * @author Monte Ohrt <monte at ohrt dot com>
 * @author Uwe Tews
 */

class LowerModifierCompiler extends Base {

	public function compile($params, \Smarty\Compiler\Template $compiler) {
		return 'mb_strtolower(' . $params[ 0 ] . ', \'' . addslashes(\Smarty\Smarty::$_CHARSET) . '\')';
	}

}