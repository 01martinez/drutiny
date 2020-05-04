<?php

namespace Drutiny\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;
use Drutiny\ExpressionLanguage;
use Symfony\Component\Yaml\Yaml;

/**
 * Audit gathered data.
 *
 * @Param(
 *  name = "expression",
 *  type = "string",
 *  default = "true",
 *  description = "The expression language to evaludate. See https://symfony.com/doc/current/components/expression_language/syntax.html"
 * )
 * @Param(
 *  name = "not_applicable",
 *  type = "string",
 *  default = "false",
 *  description = "The expression language to evaludate if the analysis is not applicable. See https://symfony.com/doc/current/components/expression_language/syntax.html"
 * )
 */
abstract class AbstractAnalysis extends Audit
{

  /**
   * Gather analysis data to audit.
   */
    abstract protected function gather(Sandbox $sandbox);

    final public function audit(Sandbox $sandbox)
    {
        $this->gather($sandbox);

        $expressionLanguage = new ExpressionLanguage($sandbox);

        $variables  = $sandbox->getParameterTokens();
        $sandbox->logger()->debug(__CLASS__ . ':TOKENS ' . Yaml::dump($variables));

        $expression = $sandbox->getParameter('not_applicable', 'false');
        $sandbox->logger()->debug(__CLASS__ . ':INAPPLICABILITY ' . $expression);
        if (@$expressionLanguage->evaluate($expression, $variables)) {
            return self::NOT_APPLICABLE;
        }

        $expression = $sandbox->getParameter('expression', 'true');
        $sandbox->logger()->info(__CLASS__ . ':EXPRESSION: ' . $expression);
        $output = @$expressionLanguage->evaluate($expression, $variables);
        $sandbox->logger()->info(__CLASS__ . ':EVALUATION: ' . json_encode($output));
        return $output;
    }
}
