<?php

namespace Smarty\Compile;

use Smarty_Internal_TemplateCompilerBase;

/**
 * Smarty Internal Plugin Compile For Class
 *
 * @package    Smarty
 * @subpackage Compiler
 */
class ForTag extends Base {

	/**
	 * Compiles code for the {for} tag
	 * Smarty supports two different syntax's:
	 * - {for $var in $array}
	 * For looping over arrays or iterators
	 * - {for $x=0; $x<$y; $x++}
	 * For general loops
	 * The parser is generating different sets of attribute by which this compiler can
	 * determine which syntax is used.
	 *
	 * @param array $args array with attributes from parser
	 * @param object $compiler compiler object
	 * @param array $parameter array with compilation parameter
	 *
	 * @return string compiled code
	 */
	public function compile($args, Smarty_Internal_TemplateCompilerBase $compiler, $parameter = [], $tag = null, $function = null) {
		$compiler->loopNesting++;
		if ($parameter === 0) {
			$this->required_attributes = ['start', 'to'];
			$this->optional_attributes = ['max', 'step'];
		} else {
			$this->required_attributes = ['start', 'ifexp', 'var', 'step'];
			$this->optional_attributes = [];
		}
		$this->mapCache = [];
		// check and get attributes
		$_attr = $this->getAttributes($compiler, $args);
		$output = "<?php\n";
		if ($parameter === 1) {
			foreach ($_attr['start'] as $_statement) {
				if (is_array($_statement['var'])) {
					$var = $_statement['var']['var'];
					$index = $_statement['var']['smarty_internal_index'];
				} else {
					$var = $_statement['var'];
					$index = '';
				}
				$output .= "\$_smarty_tpl->tpl_vars[$var] = new \\Smarty\\Variable(null, \$_smarty_tpl->isRenderingCache);\n";
				$output .= "\$_smarty_tpl->tpl_vars[$var]->value{$index} = {$_statement['value']};\n";
			}
			if (is_array($_attr['var'])) {
				$var = $_attr['var']['var'];
				$index = $_attr['var']['smarty_internal_index'];
			} else {
				$var = $_attr['var'];
				$index = '';
			}
			$output .= "if ($_attr[ifexp]) {\nfor (\$_foo=true;$_attr[ifexp]; \$_smarty_tpl->tpl_vars[$var]->value{$index}$_attr[step]) {\n";
		} else {
			$_statement = $_attr['start'];
			if (is_array($_statement['var'])) {
				$var = $_statement['var']['var'];
				$index = $_statement['var']['smarty_internal_index'];
			} else {
				$var = $_statement['var'];
				$index = '';
			}
			$output .= "\$_smarty_tpl->tpl_vars[$var] = new \\Smarty\\Variable(null, \$_smarty_tpl->isRenderingCache);";
			if (isset($_attr['step'])) {
				$output .= "\$_smarty_tpl->tpl_vars[$var]->step = $_attr[step];";
			} else {
				$output .= "\$_smarty_tpl->tpl_vars[$var]->step = 1;";
			}
			if (isset($_attr['max'])) {
				$output .= "\$_smarty_tpl->tpl_vars[$var]->total = (int) min(ceil((\$_smarty_tpl->tpl_vars[$var]->step > 0 ? $_attr[to]+1 - ($_statement[value]) : $_statement[value]-($_attr[to])+1)/abs(\$_smarty_tpl->tpl_vars[$var]->step)),$_attr[max]);\n";
			} else {
				$output .= "\$_smarty_tpl->tpl_vars[$var]->total = (int) ceil((\$_smarty_tpl->tpl_vars[$var]->step > 0 ? $_attr[to]+1 - ($_statement[value]) : $_statement[value]-($_attr[to])+1)/abs(\$_smarty_tpl->tpl_vars[$var]->step));\n";
			}
			$output .= "if (\$_smarty_tpl->tpl_vars[$var]->total > 0) {\n";
			$output .= "for (\$_smarty_tpl->tpl_vars[$var]->value{$index} = $_statement[value], \$_smarty_tpl->tpl_vars[$var]->iteration = 1;\$_smarty_tpl->tpl_vars[$var]->iteration <= \$_smarty_tpl->tpl_vars[$var]->total;\$_smarty_tpl->tpl_vars[$var]->value{$index} += \$_smarty_tpl->tpl_vars[$var]->step, \$_smarty_tpl->tpl_vars[$var]->iteration++) {\n";
			$output .= "\$_smarty_tpl->tpl_vars[$var]->first = \$_smarty_tpl->tpl_vars[$var]->iteration === 1;";
			$output .= "\$_smarty_tpl->tpl_vars[$var]->last = \$_smarty_tpl->tpl_vars[$var]->iteration === \$_smarty_tpl->tpl_vars[$var]->total;";
		}
		$output .= '?>';
		$this->openTag($compiler, 'for', ['for', $compiler->nocache]);
		// maybe nocache because of nocache variables
		$compiler->nocache = $compiler->nocache | $compiler->tag_nocache;
		// return compiled code
		return $output;
	}
}