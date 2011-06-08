<?php

/**
 * @author Daniel Robenek
 * @license MIT
 */

namespace DebugPanel;

use Nette\Object;
use Nette\IDebugPanel;
use Nette\Web\Html;
use Nette\Application\Presenter;
use Nette\Debug;
use Nette\Environment;
use Nette\Templates\FileTemplate;
use Nette\Templates\LatteFilter;
use Nette\Reflection\MethodReflection;
use Nette\Reflection\ClassReflection;
use Nette\Templates\IFileTemplate;

class PresenterLinkPanel extends Object implements IDebugPanel {

	/** @var Presenter */
	private $presenter;

	const ACTIVE = 1;
	const PARENTS = 2;
	const BOTH = 3;

	function __construct(Presenter $presenter = null) {
		$this->presenter = $presenter;
		Debug::addPanel($this);
	}

	public function getPresenter() {
		if($this->presenter === null)
			$this->presenter = Environment::getApplication()->getPresenter();
		return $this->presenter;
	}

	public function getId() {
		return "presenter-link-panel";
	}

	public function getTab() {
		$method = $this->getActionMethodReflection();
		if($method === null)
			$method = $this->getRenderMethodReflection();
		$link = self::getEditorLink($this->getPresenter()->getReflection()->getFileName(), $method === null ? $this->getPresenter()->getReflection()->getStartLine() : $method->getStartLine());
		return Html::el("span")
			->onclick("window.location='$link';event.stopPropagation();")
			->style("cursor: pointer; text-align: center; padding-left: 0; padding-right: 1px;")
			->title("Otevřít presenter [" . $this->getPresenter()->getName() . "]")
			->add(
				Html::el("img")->style("margin: 0; padding: 0;")
				->src("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAAQCAYAAAAbBi9cAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9oLGQwkDaPz2JIAAAPMSURBVDjLLc/Lb1RlHIfx7/u+55z3nLl1emcivVgteMGCBCGGVJuoCVHDwpUkGl0ajQvDxpgY2LkSBSQxKkoEjBF15YXghYWJorEKxhSV2kLbGUpnOnMuM2fO5T3vzwX+Ac8neRiICsCj6RLMkdb1nY9PVcYFdLuDdHAZyZCLpDdB0tbor8Ww3ll//5uzbPkRtA/ixxS4XwNICCB2YBF24zMMv7X/1My7x7989tr84uhdt002mitROr1r999bdzywiCSKUFEdcM8DLi4Ar15ZAcJ5IJ0BNGMgRh1UoA4//OHp759/at9eycsDRfhtgeJI5/NjJ0vb7rh3beLuCRcbZAetNYV8VoP0L6H73Dl8DYUn0LgJ+W888/qBj1/af2h/Ah2Ngps5JMpAJDOQk548dkL2lAuKCUVB1yNbmlG3E7s7No2d3zxz8RD8n6psBLEBq3/LYFmW4TYEsk4RUkj3RpOXx+7RiGP76Vde0Ah9AckIgml0fHbqyHuFC7OXH7vwW6FHLuFlIlo2kKgNxYJtgWXW7K8/y8v/zomh4Qquf/WdkE4ZllFE2PZJ8BSOLdDf029rJHrj6Fh+9tLSttVk5/STwEcG7JIZE0TQ8oyrqzW2eesWum/3HpZGCqZdASABaAbEQOoCpoXp9TZg99EvfxwuLNfDacbYJxyMOTnHZi23ZaQqZtu3b4PWLZh2HknmAojguVUg8wFTACplK9VlDtc1GYfZ9L1xAJIjRklyIfz6mhjqL5NABs4ZQv8GjCwF0hAlRwKUAlEAtb4KCwo/nP9WkkoEFwIAYEAYVru5bq1JT4xMDgEqZeR7yLEykBFCtw5QBsa6MKCRxQmC9ToatSrveq453FdqAog5MpWgG1HYaGJy/FYNbpBuBYAXIFypIccYcqaEowETgM05Us9jzVqVqzgUm0Yq14hIGVDZ9cnbN9er6wvDgMPRJlBqcpANQwN+1UMu5yCM2uBCgRGw1gioHWnK53s7EyNDV26uLTT+qozeKc9f/PPBq29/mut0XEaakWA9JFiOOFlk501EsceESczJO1RbqbMgMJNib99KFly9dBOa2P3FoJNeqfwzby4uzW9pK5Q4DMQBoDOWCcYz5iUsyjKTC81lUUMbQ7qwcbDWUy6dnfv9xDJjjBvDB6aqH7yJWjA1tXbL2MCUqdL+Picfd1edMKjrRMVcJVBWlg8du8/qMfLFASaLstg7frm6a8/x144WfZw5SgBRH4gGQFQCTeX3EQaOEEogYjMg4yDIAMgCkQF6UZ6mubFztLr3DNFD/7cGEeE/8rvtxQHf/o4AAAAASUVORK5CYII=")
			);
	}


	public function getPanel() {
		$template = new FileTemplate(__DIR__ . '/template.latte');
		$template->registerFilter(new LatteFilter());
		$template->registerHelper("editorLink", callback(__CLASS__, "getEditorLink"));
		$template->registerHelper("substr", "substr");

		$template->presenterClass = $this->getPresenter()->getReflection();
		$template->actionName = $this->getPresenter()->getAction(true);
		$template->templateFileName = $this->getTemplateFileName();
		$template->appDirPathLength = strlen(realpath(Environment::getVariable('appDir')));


		$template->interestedMethods = $this->getInterestedMethodReflections();

		$template->parentClasses = $this->getParentClasses();
		$template->componentMethods = $this->getComponentMethods();

		return $template->__toString();
	}

	protected function getInterestedMethodNames() {
		return array(
			"startup" => self::BOTH,
			$this->getActionMethodName() => self::BOTH,
			$this->getRenderMethodName() => self::BOTH,
			"beforeRender" => self::BOTH,
			"afterRender" => self::BOTH,
			"shutdown" => self::BOTH,
			"formatLayoutTemplateFiles" => self::BOTH,
			"formatTemplateFiles" => self::BOTH,
		);
	}

	private function getTemplateFileName() {
		$template = $this->getPresenter()->getTemplate();
		$templateFile = $template->getFile();
		if ($template instanceof IFileTemplate && !$template->getFile()) {
			$files = $this->getPresenter()->formatTemplateFiles($this->getPresenter()->getName(), $this->getPresenter()->getView());
			foreach ($files as $file) {
				if (is_file($file)) {
					$templateFile = $file;
					break;
				}
			}
			if (!$templateFile)
				$templateFile = str_replace(Environment::getVariable('appDir'), "\xE2\x80\xA6", reset($files));
		}
		if($templateFile !== null)
			$templateFile = realpath($templateFile);
		return $templateFile;
	}

	private function getActionMethodName() {
		return "action" . ucfirst($this->getPresenter()->getAction(false));
	}

	private function getRenderMethodName() {
		return "render" . ucfirst($this->getPresenter()->getAction(false));
	}

	private function getInterestedMethodReflections() {
		$interestedMethods = $this->getInterestedMethodNames();
		$cr = $this->getPresenter()->getReflection();
		$methods = array();
		foreach($interestedMethods as $methodName => $scope) {
			if($scope & self::ACTIVE && $cr->hasMethod($methodName)) {
				$method = $cr->getMethod($methodName);
				if($method->getDeclaringClass()->getName() == $cr->getName())
					$methods[] = $method;
			}
		}
		return $methods;
	}

	private function getParentClasses() {
		$interestedMethods = $this->getInterestedMethodNames();
		$parents = array();
		$cr = $this->getPresenter()->getReflection()->getParentClass();
		while($cr !== null && $cr->getShortName() != "Presenter") {
			$methods = array();
			foreach($interestedMethods as $methodName => $scope) {
				if($scope & self::PARENTS && $cr->hasMethod($methodName)) {
					$method = $cr->getMethod($methodName);
					if($method->getDeclaringClass()->getName() == $cr->getName())
						$methods[] = $method;
				}
			}
			$parents[] = array(
				"reflection" => $cr,
				"methods" => $methods,
			);
			$cr = $cr->getParentClass();
		}
		return $parents;
	}

	private function getComponentMethods() {
		$components = (array)$this->getPresenter()->getComponents(false);
		$methods = $this->getPresenter()->getReflection()->getMethods();
		$result = array();
		foreach($methods as $method) {
			if(strpos($method->getName(), "createComponent") === 0 && strlen($method->getName()) > 15) {
					$componentName = substr($method->getName(), 15);
					$componentName{0} = strtolower($componentName{0});
					$isUsed = isset($components[$componentName]);
					$result[] = array("method" => $method, "isUsed" => $isUsed);
			}
		}
		return $result;
	}

	private function getActionMethodReflection() {
		$method = $this->getActionMethodName();
		if($this->getPresenter()->getReflection()->hasMethod($method))
			return $this->getPresenter()->getReflection()->getMethod($method);
		else
			return null;
	}

	private function getRenderMethodReflection() {
		$method = $this->getRenderMethodName();
		if($this->getPresenter()->getReflection()->hasMethod($method))
			return $this->getPresenter()->getReflection()->getMethod($method);
		else
			return null;
	}

	public static function getEditorLink($file, $line = 1) {
		if($file instanceof MethodReflection || $file instanceof ClassReflection) {
			$line = $file->getStartLine();
			$file = $file->getFileName();
		}
		$line = (int)$line;
		return strtr(Debug::$editor, array('%file' => $file, '%line' => $line));
	}

}
