<?php

namespace Doctrine\Tests\ODM\PHPCR\Functional;

use Doctrine\ODM\PHPCR\DocumentManager;
use Doctrine\ODM\PHPCR\Exception\InvalidArgumentException;
use Doctrine\ODM\PHPCR\ReferenceManyCollection;
use Doctrine\Tests\Models\CMS\CmsArticle;
use Doctrine\Tests\Models\CMS\CmsGroup;
use Doctrine\Tests\Models\CMS\CmsTeamUser;
use Doctrine\Tests\Models\CMS\CmsUser;
use Doctrine\Tests\ODM\PHPCR\PHPCRFunctionalTestCase;
use PHPCR\NodeInterface;

class MergeTest extends PHPCRFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    /**
     * @var NodeInterface
     */
    private $node;

    public function setUp(): void
    {
        $this->dm = $this->createDocumentManager([__DIR__]);
        $this->node = $this->resetFunctionalNode($this->dm);
        $this->dm->getPhpcrSession()->save();
    }

    public function testMergeNewDocument(): void
    {
        $user = new CmsUser();
        $user->username = 'beberlei';
        $user->name = 'Benjamin';

        $mergedUser = $this->dm->merge($user);

        $this->assertNotSame($mergedUser, $user);
        $this->assertInstanceOf(CmsUser::class, $mergedUser);
        $this->assertEquals('beberlei', $mergedUser->username);
        $this->assertEquals($this->node->getPath().'/'.$mergedUser->username, $mergedUser->id, 'Merged new document should have generated path');
        $this->assertInstanceOf(ReferenceManyCollection::class, $mergedUser->articles);
    }

    public function testMergeManagedDocument(): void
    {
        $user = new CmsUser();
        $user->username = 'beberlei';
        $user->name = 'Benjamin';

        $this->dm->persist($user);
        $this->dm->flush();

        $mergedUser = $this->dm->merge($user);

        $this->assertSame($mergedUser, $user);
    }

    public function testMergeKnownDocument(): void
    {
        $user = new CmsUser();
        $user->username = 'beberlei';
        $user->name = 'Benjamin';

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        $mergedUser = $this->dm->merge($user);

        $this->assertNotSame($mergedUser, $user);
        $this->assertSame($mergedUser->id, $user->id);
    }

    public function testMergeRemovedDocument(): void
    {
        $user = new CmsUser();
        $user->username = 'beberlei';
        $user->name = 'Benjamin';

        $this->dm->persist($user);
        $this->dm->flush();

        $this->dm->remove($user);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Removed document detected during merge at '/functional/beberlei'. Cannot merge with a removed document.");
        $this->dm->merge($user);
    }

    public function testMergeWithManagedDocument(): void
    {
        $user = new CmsUser();
        $user->username = 'beberlei';
        $user->name = 'Benjamin';

        $this->dm->persist($user);
        $this->dm->flush();

        $mergableUser = new CmsUser();
        $mergableUser->id = $user->id;
        $mergableUser->username = 'jgalt';
        $mergableUser->name = 'John Galt';

        $mergedUser = $this->dm->merge($mergableUser);

        $this->assertSame($mergedUser, $user);
        $this->assertEquals('jgalt', $mergedUser->username);
        $this->assertInstanceOf(NodeInterface::class, $mergedUser->node);
    }

    public function testMergeChangeDocumentClass(): void
    {
        $user = new CmsUser();
        $user->username = 'beberlei';
        $user->name = 'Benjamin';

        $this->dm->persist($user);
        $this->dm->flush();

        $otherUser = new CmsUser();
        $otherUser->username = 'lukas';
        $otherUser->name = 'Lukas';

        $mergableGroup = new CmsGroup();
        $mergableGroup->id = $user->id;
        $mergableGroup->name = 'doctrine';

        $this->expectException(InvalidArgumentException::class);
        $this->dm->merge($mergableGroup);
    }

    public function testMergeUnknownAssignedId(): void
    {
        $doc = new CmsArticle();
        $doc->id = '/foo';
        $doc->name = 'Foo';

        $mergedDoc = $this->dm->merge($doc);

        $this->assertNotSame($mergedDoc, $doc);
        $this->assertSame($mergedDoc->id, $doc->id);
    }

    public function testMergeWithChild(): void
    {
        $user = new CmsUser();
        $user->username = 'beberlei';
        $user->name = 'Benjamin';

        $teamuser = new CmsTeamUser();
        $teamuser->username = 'jwage';
        $teamuser->name = 'Jonathan Wage';
        $teamuser->parent = $user;
        $user->child = $teamuser;

        $this->dm->persist($user);
        $this->dm->flush();

        $mergableUser = new CmsUser();
        $mergableUser->username = 'jgalt';
        $mergableUser->name = 'John Galt';
        $mergableUser->id = $user->id;

        $mergedUser = $this->dm->merge($mergableUser);

        $this->assertSame($mergedUser, $user);
        $this->assertEquals('jgalt', $mergedUser->username);
        $this->assertEquals('jwage', $mergedUser->child->username);

        $this->dm->flush();
        $mergedUser->children->count();

        $mergableUser = new CmsUser();
        $mergableUser->id = $user->id;
        $mergableUser->username = 'dbu';
        $mergableUser->name = 'David';

        $mergedUser = $this->dm->merge($mergableUser);

        $this->assertSame($mergedUser, $user);
        $this->assertEquals('dbu', $mergedUser->username);
        $this->assertEquals('David', $mergedUser->name);
        $this->assertEquals('jwage', $mergedUser->child->username);

        $this->dm->flush();
    }

    public function testMergeWithChildren(): void
    {
        $user = new CmsUser();
        $user->username = 'beberlei';
        $user->name = 'Benjamin';

        $teamuser = new CmsTeamUser();
        $teamuser->username = 'jwage';
        $teamuser->name = 'Jonathan Wage';
        $teamuser->parent = $user;

        $this->dm->persist($user);
        $this->dm->persist($teamuser);
        $this->dm->flush();
        $this->dm->clear();

        $user = $this->dm->find(null, '/functional/beberlei');
        $this->assertCount(1, $user->children);

        $mergableUser = new CmsUser();
        $mergableUser->username = 'jgalt';
        $mergableUser->name = 'John Galt';
        $mergableUser->id = $user->id;

        $mergedUser = $this->dm->merge($mergableUser);

        $this->assertSame($mergedUser, $user);
        $this->assertEquals('jgalt', $mergedUser->username);
        $this->assertCount(1, $mergedUser->children);

        $this->dm->flush();
        $mergedUser->children->count();

        $mergableUser = new CmsUser();
        $mergableUser->id = $user->id;
        $mergableUser->username = 'dbu';

        $mergedUser = $this->dm->merge($mergableUser);

        $this->assertSame($mergedUser, $user);
        $this->assertEquals('dbu', $mergedUser->username);
        $this->assertNull($mergedUser->name);
        $this->assertCount(1, $mergedUser->children);

        $this->dm->flush();

        $mergedUser = $this->dm->find(null, $mergedUser->id);
        $this->assertCount(1, $mergedUser->children);
    }
}
