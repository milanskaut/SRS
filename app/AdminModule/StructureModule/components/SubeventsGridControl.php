<?php

namespace App\AdminModule\StructureModule\Components;

use App\Model\ACL\Role;
use App\Model\Program\BlockRepository;
use App\Model\Structure\SubeventRepository;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Control;
use Ublaboo\DataGrid\DataGrid;


/**
 * Komponenta pro správu podakcí.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class SubeventsGridControl extends Control
{
    /** @var Translator */
    private $translator;

    /** @var SubeventRepository */
    private $subeventRepository;

    /** @var BlockRepository */
    private $blockRepository;


    /**
     * SubeventsGridControl constructor.
     * @param Translator $translator
     * @param SubeventRepository $subeventRepository
     * @param BlockRepository $blockRepository
     */
    public function __construct(Translator $translator, SubeventRepository $subeventRepository,
                                BlockRepository $blockRepository)
    {
        parent::__construct();

        $this->translator = $translator;
        $this->subeventRepository = $subeventRepository;
        $this->blockRepository = $blockRepository;
    }

    /**
     * Vykreslí komponentu.
     */
    public function render()
    {
        $this->template->render(__DIR__ . '/templates/subevents_grid.latte');
    }

    /**
     * Vytvoří komponentu.
     * @param $name
     */
    public function createComponentSubeventsGrid($name)
    {
        $grid = new DataGrid($this, $name);
        $grid->setTranslator($this->translator);
        $grid->setDataSource($this->subeventRepository->createQueryBuilder('s'));
        $grid->setDefaultSort(['name' => 'ASC']);
        $grid->setPagination(FALSE);


        $grid->addColumnText('name', 'admin.structure.subevents_name');

        $grid->addColumnText('implicit', 'admin.structure.subevents_implicit')
            ->setReplacement([
                '0' => $this->translator->translate('admin.common.no'),
                '1' => $this->translator->translate('admin.common.yes')
            ]);

        $grid->addColumnNumber('fee', 'admin.structure.subevents_fee');

        $grid->addColumnText('capacity', 'admin.structure.subevents_capacity')
            ->setRendererOnCondition(function ($row) {
                return $this->translator->translate('admin.structure.subevents_capacity_unlimited');
            }, function ($row) {
                return $row->getCapacity() === NULL;
            }
            );


        $grid->addToolbarButton('Subevents:add')
            ->setIcon('plus')
            ->setTitle('admin.common.add');

        $grid->addAction('edit', 'admin.common.edit', 'Subevents:edit');
        $grid->allowRowsAction('edit', function ($item) {
            return !$item->isImplicit();
        });

        $grid->addAction('delete', '', 'delete!')
            ->setIcon('trash')
            ->setTitle('admin.common.delete')
            ->setClass('btn btn-xs btn-danger')
            ->addAttributes([
                'data-toggle' => 'confirmation',
                'data-content' => $this->translator->translate('admin.structure.subevents_delete_confirm')
            ]);
        $grid->allowRowsAction('delete', function ($item) {
            return !$item->isImplicit();
        });
    }

    /**
     * Zpracuje odstranění podakce.
     * @param $id
     */
    public function handleDelete($id)
    {
        $subevent = $this->subeventRepository->findById($id);

        $blocksInSubevent = $subevent->getBlocks();
        $implicitSubevent = $this->subeventRepository->findImplicit();

        foreach ($blocksInSubevent as $block) {
            $block->setSubevent($implicitSubevent);
            $this->blockRepository->save($block);
        }

        $this->subeventRepository->remove($subevent);

        $this->getPresenter()->flashMessage('admin.structure.subevents_deleted', 'success');

        $this->redirect('this');
    }
}
