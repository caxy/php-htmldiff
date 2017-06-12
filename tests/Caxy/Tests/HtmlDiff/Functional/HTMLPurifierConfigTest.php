<?php

namespace Caxy\Tests\HtmlDiff\Functional;

use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\HtmlDiffConfig;
use Caxy\Tests\AbstractTest;

class HTMLPurifierConfigTest extends AbstractTest
{
    /**
     * @var \HTMLPurifier_Config
     */
    protected $config;

    public function setUp()
    {
        $config = \HTMLPurifier_Config::createDefault();

        $this->config = $this
            ->getMockBuilder('\\HTMLPurifier_Config')
            ->disableOriginalConstructor()
            ->getMock();

        $this->config->expects($this->atLeastOnce())
            ->method('set')
            ->with($this->anything(), $this->anything())
        ;

        $this->config->expects($this->any())
            ->method('getHTMLDefinition')
            ->will($this->returnValue($config->getHTMLDefinition()));

        $this->config->expects($this->any())
            ->method('get')
            ->will($this->returnCallback(function ($argument) {
                $config = \HTMLPurifier_Config::createDefault();

                return $config->get($argument);
            }));

        $this->config->expects($this->any())
            ->method('getBatch')
            ->will($this->returnCallback(function ($argument) {
                $config = \HTMLPurifier_Config::createDefault();

                return $config->getBatch($argument);
            }));
    }

    public function testHtmlDiffConfigTraditional()
    {
        $oldText = '<b>text</b>';
        $newText = '<b>t3xt</b>';

        $diff = new HtmlDiff(trim($oldText), trim($newText), 'UTF-8', array());

        $diff->getConfig()->setPurifierCacheLocation('/tmp');
        $diff->setHTMLPurifierConfig($this->config);

        $diff->build();
    }

    public function testHtmlDiffConfigStatic()
    {
        $oldText = '<b>text</b>';
        $newText = '<b>t3xt</b>';

        $config = new HtmlDiffConfig();
        $config->setPurifierCacheLocation('/tmp');

        $diff = HtmlDiff::create($oldText, $newText, $config);
        $diff->setHTMLPurifierConfig($this->config);
        $diff->build();
    }
}