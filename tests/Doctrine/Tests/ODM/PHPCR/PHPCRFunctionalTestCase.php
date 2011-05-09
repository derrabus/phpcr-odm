<?php

namespace Doctrine\Tests\ODM\PHPCR;

abstract class PHPCRFunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    public function createDocumentManager()
    {
        $reader = new \Doctrine\Common\Annotations\AnnotationReader();
        $reader->setDefaultAnnotationNamespace('Doctrine\ODM\PHPCR\Mapping\\');
        $paths = __DIR__ . "/../../Models";
        $metaDriver = new \Doctrine\ODM\PHPCR\Mapping\Driver\AnnotationDriver($reader, $paths);

        $url = isset($GLOBALS['DOCTRINE_PHPCR_REPOSITORY']) ? $GLOBALS['DOCTRINE_PHPCR_REPOSITORY'] : 'http://127.0.0.1:8080/server/';
        $workspace = isset($GLOBALS['DOCTRINE_PHPCR_WORKSPACE']) ? $GLOBALS['DOCTRINE_PHPCR_WORKSPACE'] : 'tests';
        $user = isset($GLOBALS['DOCTRINE_PHPCR_USER']) ? $GLOBALS['DOCTRINE_PHPCR_USER'] : '';
        $pass = isset($GLOBALS['DOCTRINE_PHPCR_PASS']) ? $GLOBALS['DOCTRINE_PHPCR_PASS'] : '';

        $repository = new \Jackalope\Repository(new \Jackalope\Factory, $url);
        $credentials = new \PHPCR\SimpleCredentials($user, $pass);
        $session = $repository->login($credentials, $workspace);

        $config = new \Doctrine\ODM\PHPCR\Configuration();
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setMetadataDriverImpl($metaDriver);
        $config->setPhpcrSession($session);

        return \Doctrine\ODM\PHPCR\DocumentManager::create($config);
    }

    public function resetFunctionalNode($dm)
    {
        $session = $dm->getPhpcrSession();
        $root = $session->getNode('/');
        if ($root->hasNode('functional')) {
            $root->getNode('functional')->remove();
            $session->save();
        }
        $node = $root->addNode('functional');
        $session->save();
        return $node;
   }
}
