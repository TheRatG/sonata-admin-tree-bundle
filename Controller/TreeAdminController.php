<?php

namespace RedCode\TreeBundle\Controller;

use Doctrine\ORM\EntityManager;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TreeAdminController extends CRUDController
{
    public function listAction()
    {
        $request = $this->getRequest();
        if ($listMode = $request->get('_list_mode')) {
            $this->admin->setListMode($listMode);
        }
        $listMode = $this->admin->getListMode();

        if ($listMode === 'tree') {
            $this->admin->checkAccess('list');

            $preResponse = $this->preList($request);
            if ($preResponse !== null) {
                return $preResponse;
            }

            return $this->render(
                $this->getParameter('red_code_tree.template'),
                [
                    'action' => 'list',
                    'csrf_token' => $this->getCsrfToken('sonata.batch'),
                ],
                null,
                $request
            );
        }

        return parent::listAction();
    }

    public function treeDataAction()
    {
        $request = $this->getRequest();

        $repo = $this->getRepository();

        $operation = $request->get('operation');
        switch ($operation) {
            case 'get_node':
                $nodeId = $request->get('id');
                if ($nodeId) {
                    $parentNode = $repo->find($nodeId);
                    $nodes = $repo->getChildren($parentNode, true);
                } else {
                    $nodes = $repo->getRootNodes();
                }

                $nodes = array_map(
                    function ($node) {
                        $cnt = $node->getChildren()->count();
                        return [
                            'id' => $node->getId(),
                            'text' => $node->{'get' . ucfirst($this->admin->getTreeTextField())}(),
                            'children' => $cnt > 0,
                            'type' => $cnt > 0 ? 'default' : 'file',
                        ];
                    },
                    $nodes
                );

                return new JsonResponse($nodes);
            case 'move_node':
                $nodeId = $request->get('id');
                $parentNodeId = $request->get('parent_id');

                $parentNode = $repo->find($parentNodeId);
                $node = $repo->find($nodeId);
                $node->setParent($parentNode);

                $this->admin->getModelManager()->update($node);

                $siblings = $repo->getChildren($parentNode, true);
                $position = $request->get('position');
                $i = 0;

                foreach ($siblings as $sibling) {
                    if ($sibling->getId() === $node->getId()) {
                        break;
                    }

                    $i++;
                }

                $diff = $position - $i;

                if ($diff > 0) {
                    $repo->moveDown($node, $diff);
                } else {
                    $repo->moveUp($node, abs($diff));
                }

                return new JsonResponse(
                    [
                        'id' => $node->getId(),
                        'text' => $node->{'get' . ucfirst($this->admin->getTreeTextField())}(),
                    ]
                );
            case 'rename_node':
                $nodeId = $request->get('id');
                $nodeText = $request->get('text');
                $node = $repo->find($nodeId);

                $node->{'set' . ucfirst($this->admin->getTreeTextField())}($nodeText);
                $this->admin->getModelManager()->update($node);

                return new JsonResponse(
                    [
                        'id' => $node->getId(),
                        'text' => $node->{'get' . ucfirst($this->admin->getTreeTextField())}(),
                    ]
                );
            case 'create_node':
                $parentNodeId = $request->get('parent_id');
                $parentNode = $repo->find($parentNodeId);
                $nodeText = $request->get('text');
                $node = $this->admin->getNewInstance();
                $node->{'set' . ucfirst($this->admin->getTreeTextField())}($nodeText);
                $node->setParent($parentNode);
                $this->admin->getModelManager()->create($node);

                return new JsonResponse(
                    [
                        'id' => $node->getId(),
                        'text' => $node->{'get' . ucfirst($this->admin->getTreeTextField())}(),
                    ]
                );
            case 'delete_node':
                $nodeId = $request->get('id');
                $node = $repo->find($nodeId);
                $this->admin->getModelManager()->delete($node);

                return new JsonResponse();
        }

        throw new BadRequestHttpException('Unknown action for tree');
    }

    public function moveUpAction(int $id)
    {
        $object = $this->admin->getObject($id);
        if (!$object) {
            throw $this->createNotFoundException();
        }

        $this->getRepository()->moveUp($object, 1);

        $this->addFlash(
            'sonata_flash_error',
            $this->trans('flash_move_up_success', [], 'RedCodeTreeBundle')
        );

        return $this->redirectToList();
    }

    public function moveDownAction(int $id)
    {
        $object = $this->admin->getObject($id);
        if (!$object) {
            throw $this->createNotFoundException();
        }
        $this->getRepository()->moveDown($object, 1);

        $this->addFlash(
            'sonata_flash_error',
            $this->trans('flash_move_down_success', [], 'RedCodeTreeBundle')
        );

        return $this->redirectToList();
    }

    /**
     * @return NestedTreeRepository
     */
    protected function getRepository(): NestedTreeRepository
    {
        $doctrine = $this->get('doctrine');
        /** @var EntityManager $em */
        $em = $doctrine->getManagerForClass($this->admin->getClass());
        /** @var NestedTreeRepository $repo */
        $repo = $em->getRepository($this->admin->getClass());
        return $repo;
    }
}
