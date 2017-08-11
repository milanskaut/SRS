<?php

namespace App\AdminModule\Forms;

use App\Model\ACL\Role;
use App\Model\ACL\RoleRepository;
use App\Model\User\User;
use App\Model\User\UserRepository;
use App\Services\FilesService;
use Nette;
use Nette\Application\UI\Form;


/**
 * Formulář pro vytvoření externího lektora.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class AddLectorForm extends Nette\Object
{
    /** @var BaseForm */
    private $baseFormFactory;

    /** @var UserRepository */
    private $userRepository;

    /** @var RoleRepository */
    private $roleRepository;

    /** @var FilesService */
    private $filesService;


    /**
     * AddLectorForm constructor.
     * @param BaseForm $baseFormFactory
     * @param UserRepository $userRepository
     * @param RoleRepository $roleRepository
     * @param FilesService $filesService
     */
    public function __construct(BaseForm $baseFormFactory, UserRepository $userRepository,
                                RoleRepository $roleRepository, FilesService $filesService)
    {
        $this->baseFormFactory = $baseFormFactory;
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
        $this->filesService = $filesService;
    }

    /**
     * Vytvoří formulář.
     * @return Form
     */
    public function create()
    {
        $form = $this->baseFormFactory->create();

        $form->addUpload('photo', 'admin.users.users_photo')
            ->setAttribute('accept', 'image/*')
            ->addCondition(Form::FILLED)
            ->addRule(Form::IMAGE, 'admin.users.users_photo_format');

        $form->addText('firstName', 'admin.users.users_firstname')
            ->addRule(Form::FILLED, 'admin.users.users_firstname_empty');

        $form->addText('lastName', 'admin.users.users_lastname')
            ->addRule(Form::FILLED, 'admin.users.users_lastname_empty');

        $form->addText('nickName', 'admin.users.users_nickname');

        $form->addText('email', 'admin.users.users_email')
            ->addCondition(Form::FILLED)
            ->addRule(Form::EMAIL, 'admin.users.users_email_format');

        $form->addDatePicker('birthdate', 'admin.users.users_birthdate');

        $form->addText('street', 'admin.users.users_street')
            ->addCondition(Form::FILLED)
            ->addRule(Form::PATTERN, 'web.application_content.street_format', '^(.*[^0-9]+) (([1-9][0-9]*)/)?([1-9][0-9]*[a-cA-C]?)$');

        $form->addText('city', 'admin.users.users_city');

        $form->addText('postcode', 'admin.users.users_postcode')
            ->addCondition(Form::FILLED)
            ->addRule(Form::PATTERN, 'web.application_content.postcode_format', '^\d{3} ?\d{2}$');

        $form->addText('state', 'admin.users.users_state');

        $form->addTextArea('about', 'admin.users.users_about_me');

        $form->addTextArea('privateNote', 'admin.users.users_private_note');

        $form->addSubmit('submit', 'admin.common.save');

        $form->addSubmit('cancel', 'admin.common.cancel')
            ->setValidationScope([])
            ->setAttribute('class', 'btn btn-warning');

        $form->onSuccess[] = [$this, 'processForm'];

        return $form;
    }

    /**
     * Zpracuje formulář.
     * @param Form $form
     * @param \stdClass $values
     */
    public function processForm(Form $form, \stdClass $values)
    {
        if (!$form['cancel']->isSubmittedBy()) {
            $user = new User();

            $user->setFirstName($values['firstName']);
            $user->setLastName($values['lastName']);
            $user->setNickName($values['nickName']);
            $user->setEmail($values['email']);
            $user->setBirthdate(new \DateTime($values['birthdate']));
            $user->setStreet($values['street']);
            $user->setCity($values['city']);
            $user->setPostcode($values['postcode']);
            $user->setState($values['state']);
            $user->setAbout($values['about']);
            $user->setNote($values['privateNote']);

            $user->addRole($this->roleRepository->findBySystemName(Role::LECTOR));

            $this->userRepository->save($user);

            $photo = $values['photo'];
            if ($photo->size > 0) {
                $photoExtension = image_type_to_extension(getimagesizefromstring($photo->getContents())[2]);
                $photoName = $user->getId() . $photoExtension;

                $this->filesService->save($photo, '/user_photos/' . $photoName);
                $this->filesService->resizeAndCropImage('/user_photos/' . $photoName, 135, 180);

                $user->setPhoto($photoName);
            }

            $this->userRepository->save($user);
        }
    }
}
