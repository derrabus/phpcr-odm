<?php

namespace Doctrine\Tests\ODM\PHPCR\Functional;

use Doctrine\ODM\PHPCR\DocumentManager;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Tests\Models\CMS\CmsPage;
use Doctrine\Tests\ODM\PHPCR\PHPCRFunctionalTestCase;

/**
 * These tests ensure that you can reset a value in a lifecycle event.
 *
 * A use case is for example a bridge which allows you to store associations to objects in a different database
 * - In the prePersist and preUpdate event you serialize the identifier reference of the object
 * - In the postLoad, postPersist and postUpdate event you unserialize the reference and convert it back to the original object
 */
class EventManagerResetTest extends PHPCRFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp(): void
    {
        $this->dm = $this->createDocumentManager();
        $this->resetFunctionalNode($this->dm);
        $this->dm->getEventManager()->addEventListener([
            'prePersist', 'postPersist', 'preUpdate', 'postUpdate',
        ], new TestResetListener());
    }

    public function testResetEvents(): void
    {
        $page = new CmsPage();
        $page->title = 'my-page';

        $pageContent = new CmsPageContent();
        $pageContent->id = 1;
        $pageContent->content = 'long story';
        $pageContent->formatter = 'plaintext';

        $page->content = $pageContent;

        $this->dm->persist($page);

        $this->assertEquals(serialize(['id' => $pageContent->id]), $page->content);

        $this->dm->flush();

        $this->assertInstanceOf(CmsPageContent::class, $page->content);

        // This is required as the originalData in the UnitOfWork doesn’t set the node of the Document
        $this->dm->clear();

        $pageLoaded = $this->dm->getRepository(CmsPage::class)->find($page->id);

        $pageLoaded->title = 'my-page-changed';

        $this->assertEquals('my-page-changed', $pageLoaded->title);

        $this->dm->flush();

        $this->assertEquals('my-page', $pageLoaded->title);

        $pageLoaded->content = $pageContent;

        $this->dm->persist($pageLoaded);

        $this->dm->flush();

        $this->assertInstanceOf(CmsPageContent::class, $page->content);
    }
}

class TestResetListener
{
    public function prePersist(LifecycleEventArgs $e): void
    {
        $document = $e->getObject();
        if ($document instanceof CmsPage && $document->content instanceof CmsPageContent) {
            $contentReference = ['id' => $document->content->id];
            $document->content = serialize($contentReference);
        }
    }

    public function postPersist(LifecycleEventArgs $e): void
    {
        $document = $e->getObject();
        if ($document instanceof CmsPage) {
            $contentReference = unserialize($document->content);

            if (false !== $contentReference && isset($contentReference['id'])) {
                // Load real object using $contentReference['id']
                $pageContent = new CmsPageContent();
                $pageContent->id = 1;

                $document->content = $pageContent;
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $e): void
    {
        $document = $e->getObject();
        if ($document instanceof CmsPage && 'my-page' !== $document->title) {
            $document->title = 'my-page';
        }

        if ($document instanceof CmsPage && $document->content instanceof CmsPageContent) {
            $contentReference = ['id' => $document->content->id];
            $document->content = serialize($contentReference);
        }
    }

    public function postUpdate(LifecycleEventArgs $e): void
    {
        $document = $e->getObject();
        if ($document instanceof CmsPage) {
            $contentReference = unserialize($document->content);

            if (false !== $contentReference && isset($contentReference['id'])) {
                // Load real object using $contentReference['id']
                $pageContent = new CmsPageContent();
                $pageContent->id = 1;

                $document->content = $pageContent;
            }
        }
    }
}

class CmsPageContent
{
    public $id;

    public $content;

    public $formatter;
}
