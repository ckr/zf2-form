<?php
/**
 * Renderer for the jquery.validate plugin
 *
 * @category  StrokerForm
 * @package   StrokerForm\Renderer
 * @copyright 2012 Bram Gerritsen
 * @version   SVN: $Id$
 */

namespace StrokerForm\Renderer\JqueryValidate;

use Zend\View\Renderer\PhpRenderer as View;
use Zend\Form\FormInterface;
use StrokerForm\Renderer\AbstractValidateRenderer;
use Zend\Validator\ValidatorInterface;
use Zend\Form\ElementInterface;

class Renderer extends AbstractValidateRenderer
{
    /**
     * @var array
     */
    private $rules = array();

    /**
     * @var array
     */
    private $messages = array();

    /**
     * @var array
     */
    protected $skipValidators = array(
        'InArray',
        'Explode',
        'Upload'
    );

    /**
     * Executed before the ZF2 view helper renders the element
     *
     * @param string                          $formAlias
     * @param \Zend\View\Renderer\PhpRenderer $view
     * @param \Zend\Form\FormInterface        $form
     * @param array                           $options
     */
    public function preRenderForm($formAlias, View $view, FormInterface $form = null, array $options = array())
    {
        if ($form === null) {
            $form = $this->getFormManager()->get($formAlias);
        }

        parent::preRenderForm($formAlias, $view, $form, $options);

        /** @var $options Options */
        $options = $this->getOptions($options);

        $inlineScript = $view->plugin('inlineScript');
        $inlineScript->appendScript($this->getInlineJavascript($form, $options));

        if ($options->isIncludeAssets()) {
            $assetBaseUri = $this->getHttpRouter()->assemble(array(), array('name' => 'strokerform-asset'));
            $inlineScript->appendFile($assetBaseUri . '/jquery_validate/js/jquery.validate.js');
            if ($options->isUseTwitterBootstrap() === true) {
                $inlineScript->appendFile($assetBaseUri . '/jquery_validate/js/jquery.validate.bootstrap.js');
            }
        }
    }

    /**
     * @param  \Zend\Form\FormInterface $form
     * @param Options $options
     * @return string
     */
    protected function getInlineJavascript(FormInterface $form, Options $options)
    {
        $validateOptions = implode(',', $options->getValidateOptions());
        if (!empty($validateOptions)) {
            $validateOptions .= ',';
        }

        return sprintf($options->getInitializeTrigger(), '
        $(\'form[name="' . $form->getName() . '"]\').validate({' . $validateOptions . '
        "rules": ' . \Zend\Json\Json::encode($this->rules) . ',
        "messages": ' . \Zend\Json\Json::encode($this->messages) . '
        });');
    }

    /**
     * @param string $formAlias
     * @param \Zend\Form\ElementInterface        $element
     * @param \Zend\Validator\ValidatorInterface $validator
     * @return mixed|void
     */
    protected function addValidationAttributesForElement($formAlias, ElementInterface $element, ValidatorInterface $validator = null)
    {
        if ($element instanceof \Zend\Form\Element\Email && $validator instanceof \Zend\Validator\Regex) {
            $validator = new \Zend\Validator\EmailAddress();
        }
        if (in_array($this->getValidatorClassName($validator), $this->skipValidators)) {
            return;
        }
        $rule = $this->getRule($validator);
        if ($rule !== null) {
            $rules = $rule->getRules($validator);
            $messages = $rule->getMessages($validator);
        } else {
            //fallback ajax
            $ajaxUri = $this->getHttpRouter()->assemble(array('form' => $formAlias), array('name' => 'strokerform-ajax-validate'));
            $rules = array(
                'remote' => array(
                    'url' => $ajaxUri,
                    'type' => 'POST'
                )
            );
            $messages = array();
        }

        $elementName = $this->getElementName($element);

        if (!isset($this->rules[$elementName])) {
            $this->rules[$elementName] = array();
        }
        $this->rules[$elementName] = array_merge($this->rules[$elementName], $rules);
        if (!isset($this->messages[$elementName])) {
            $this->messages[$elementName] = array();
        }
        $this->messages[$elementName] = array_merge($this->messages[$elementName], $messages);
    }

    /**
     * Get the classname of the zend validator
     *
     * @param  \Zend\Validator\ValidatorInterface $validator
     * @return mixed
     */
    protected function getValidatorClassName(ValidatorInterface $validator = null)
    {
        $namespaces = explode('\\', get_class($validator));

        return end($namespaces);
    }

    /**
     * @param  \Zend\Validator\ValidatorInterface $validator
     * @return null|Rule\AbstractRule
     */
    protected function getRule(ValidatorInterface $validator = null)
    {
        $ruleClass = 'StrokerForm\\Renderer\\JqueryValidate\\Rule\\' . $this->getValidatorClassName($validator);
        if (class_exists($ruleClass)) {
            /** @var $rule Rule\AbstractRule */
            $rule = new $ruleClass;
            $rule->setTranslator($this->getTranslator());
            $rule->setTranslatorEnabled($this->isTranslatorEnabled());
            $rule->setTranslatorTextDomain($this->getTranslatorTextDomain());

            return $rule;
        }

        return null;
    }
}
