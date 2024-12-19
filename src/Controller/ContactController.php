<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ContactRepository;
use App\Entity\Contact;

class ContactController extends AbstractController
{
    #[Route('/api/contacts', name: 'app_contact', methods: ['GET'])]
    public function getContacts(ContactRepository $contactRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    {
        $contactList  =  $contactRepository->getContacts();
        $jsonContactList = $serializer->serialize($contactList, 'json');

        // Fetch all contacts in database
        $allContactsInDatabase = $entityManager->getRepository(Contact::class)->findAll();
        
        // Persist each contact in database
        // Check before if contact exist in database
        if (count($allContactsInDatabase) !== count($contactList)) {
            $contactsArray = array_map(null, $contactList);
            for ($i=0; $i < count($contactsArray) ; $i++) {
                if (isset($contactsArray[$i]) && !in_array($contactsArray[$i],$allContactsInDatabase)) {
                    $contact = new Contact;
                    $contact->setNom($contactsArray[$i]['0']);
                    $contact->setCpostalp($contactsArray[$i]['2']);
                    $contact->setVillep($contactsArray[$i]['4']);
                    $contact->setIdContact($contactsArray[$i]['6']);

                    // tell Doctrine you want to (eventually) save the Product (no queries yet)
                    $entityManager->persist($contact);

                }
            }
            // actually executes the queries (i.e. the INSERT query)
            $entityManager->flush();
        }


        return new JsonResponse(
            "Contacts sur API KIZEO : " . count($contactList) .
            " | Contacts en BDD : " . count($allContactsInDatabase) . 
            "   \n La liste de contacts a bien été mise à jour, vous pouvez revenir en arrière"
            , Response::HTTP_OK, [], true);
    }
}
