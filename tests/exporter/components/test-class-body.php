<?php

use \Exporter\Components\Body as Body;
use \Exporter\Settings as Settings;
use \Exporter\Component_Layouts as Component_Layouts;
use \Exporter\Component_Styles as Component_Styles;

class Body_Test extends PHPUnit_Framework_TestCase {

	public function setup() {
		$this->settings = new Settings();
		$this->styles   = new Component_Styles();
		$this->layouts  = new Component_Layouts();
	}

	public function testBuildingRemovesTags() {
		$body_component = new Body( '<p>my text</p>', null, $this->settings,
			$this->styles, $this->layouts );

		$this->assertEquals(
			array(
				'text' => "my text\n\n",
				'role' => 'body',
				'format' => 'markdown',
				'textStyle' => 'dropcapBodyStyle',
		 	),
			$body_component->value()
		);
	}

	public function testWithoutDropcap() {
		$this->settings->set( 'initial_dropcap', 'no' );
		$body_component = new Body( '<p>my text</p>', null, $this->settings,
			$this->styles, $this->layouts );

		$this->assertEquals(
			array(
				'text' => "my text\n\n",
				'role' => 'body',
				'format' => 'markdown',
				'textStyle' => 'default-body',
		 	),
			$body_component->value()
		);
	}

}

