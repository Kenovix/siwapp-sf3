<?php

namespace Siwapp\OrderBundle\Controller;

use Siwapp\OrderBundle\Form\ExcelType;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Siwapp\CoreBundle\Controller\AbstractInvoiceController;
use Siwapp\CoreBundle\Entity\Item;
use Siwapp\OrderBundle\Entity\Order;
use Siwapp\OrderBundle\Form\OrderType;

/**
 * @Route("/estimate")
 */
class OrderController extends AbstractInvoiceController
{
    /**
     * @Route("", name="order_index")
     * @Template("SiwappOrderBundle:Order:index.html.twig")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('SiwappOrderBundle:Order');
        $repo->setPaginator($this->get('knp_paginator'));
        // @todo Unhardcode this.
        $limit = 50;

        $form = $this->createForm('Siwapp\OrderBundle\Form\SearchOrderType', null, [
            'action' => $this->generateUrl('order_index'),
            'method' => 'GET',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $pagination = $repo->paginatedSearch($form->getData(), $limit, $request->query->getInt('page', 1));
        } else {
            $pagination = $repo->paginatedSearch([], $limit, $request->query->getInt('page', 1));
        }

        $listForm = $this->createForm('Siwapp\OrderBundle\Form\OrderListType', $pagination->getItems(), [
            'action' => $this->generateUrl('order_index'),
        ]);
        $listForm->handleRequest($request);
        if ($listForm->isSubmitted() && $listForm->isValid()) {
            $data = $listForm->getData();
            if (empty($data['orders'])) {
                $this->addTranslatedMessage('flash.nothing_selected', 'warning');
            }
            else {
                if ($request->request->has('delete')) {
                    return $this->bulkDelete($data['orders']);
                } elseif ($request->request->has('pdf')) {
                    return $this->bulkPdf($data['orders']);
                } elseif ($request->request->has('print')) {
                    return $this->bulkPrint($data['orders']);
                } elseif ($request->request->has('email')) {
                    return $this->bulkEmail($data['orders']);
                }
            }
        }

        $excelForm = $this->createForm('Siwapp\OrderBundle\Form\ExcelType', null, [
            'action'  => $this->generateUrl('order_add'),
            'method'  => 'POST',
        ]);

        return array(
            'orders' => $pagination,
            'currency' => $em->getRepository('SiwappConfigBundle:Property')->get('currency', 'EUR'),
            'search_form' => $form->createView(),
            'list_form' => $listForm->createView(),
            'excel_form' => $excelForm->createView()
        );
    }

    /**
     * @Route("/{id}/show", name="order_show")
     * @Template("SiwappOrderBundle:Order:show.html.twig")
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('SiwappOrderBundle:Order')->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Order entity.');
        }

        if ($entity->isDraft() || $entity->isPending()) {
            return $this->redirect($this->generateUrl('order_edit', ['id' => $id]));
        }

        return array(
            'entity' => $entity,
            'currency' => $em->getRepository('SiwappConfigBundle:Property')->get('currency', 'EUR'),
        );
    }

    /**
     * @Route("/{id}/show/print", name="order_show_print")
     */
    public function showPrintAction($id)
    {
        $order = $this->getDoctrine()
            ->getRepository('SiwappOrderBundle:Order')
            ->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Unable to find Order entity.');
        }

        return new Response($this->getOrderPrintPdfHtml($order, true));
    }

    /**
     * @Route("/{id}/show/pdf", name="order_show_pdf")
     */
    public function showPdfAction($id)
    {
        $order = $this->getDoctrine()
            ->getRepository('SiwappOrderBundle:Order')
            ->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Unable to find Order entity.');
        }

        $html = $this->getOrderPrintPdfHtml($order);

        return new Response(
            $this->getPdf($html),
            200,
            array(
                'Content-Type'          => 'application/pdf',
                'Content-Disposition'   => 'attachment; filename="Estimate-' . $order->label() . '.pdf"'
            )
        );
    }

    /**
     * @Route("/add", name="order_add")
     * @param Request $request
     * @Template("SiwappOrderBundle:Order:edit.html.twig")
     */
    public function addAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $order = new Order();
        $order->addItem(new Item());
        $terms = $em->getRepository('SiwappConfigBundle:Property')->get('legal_terms');
        if ($terms) {
            $order->setTerms($terms);
        }

        $form = $this->createForm(OrderType::class, $order, [
            'action' => $this->generateUrl('order_add'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $customerEntity = $em->getRepository('SiwappCustomerBundle:Customer')->findOneBy(array('identification' => $order->getCustomerIdentification()));

            // Si no se encontró el cliente, se le notifica al usuario que debe darlo de alta
            if (!$customerEntity) {

                $this->addTranslatedMessage('flash.unregistered_customer', 'danger');

            } else {

                $em->persist($order);
                $em->flush();
                $this->addTranslatedMessage('flash.added');

                return $this->redirect($this->generateUrl('order_edit', array('id' => $order->getId())));

            }
        }

        // Se verifica si se está tratando de importar un excel
        if ($request->request->has('load_excel')) {

            /** @var UploadedFile $file */
            $file = $request->files->get('excel')['file'];

            if ( $file ) {
                // Se inicializa el lector del archivo excel
                $phpExcelObject = $this->get('phpexcel')->createPHPExcelObject($file->getRealPath());
                
                // Se activa la primera hoja del libro
                $phpExcelObject->setActiveSheetIndex(0);
                // Se obtiene la hoja activa
                $sheet = $phpExcelObject->getActiveSheet();
                // Se definen los campos a leer del excel
                $customerName = $sheet->rangeToArray('G6')[0][0];
                $customerIdentification = $sheet->rangeToArray('G8')[0][0];
                $customerEmail = $sheet->rangeToArray('G10')[0][0];
                $customerAddress = $sheet->rangeToArray('G12')[0][0];
                $issueDate = $sheet->rangeToArray('B13')[0][0];

                $customerEntity = $em->getRepository('SiwappCustomerBundle:Customer')->findOneBy(array('identification' => $customerIdentification));

                // Si no se encontró el cliente, se le notifica al usuario que debe darlo de alta
                if (!$customerEntity) {

                    $this->addTranslatedMessage('flash.unregistered_customer', 'danger');

                    $order->setCustomerName($customerName);
                    $order->setCustomerIdentification($customerIdentification);
                    $order->setCustomerEmail($customerEmail);
                    $order->setInvoicingAddress($customerAddress);
                    $order->setShippingAddress($customerAddress);

                } else {

                    $order->setCustomer($customerEntity);
                    $order->setCustomerName($customerEntity->getName());
                    $order->setCustomerIdentification($customerEntity->getIdentification());
                    $order->setCustomerEmail($customerEntity->getEmail());
                    $order->setInvoicingAddress($customerEntity->getInvoicingAddress());
                    $order->setShippingAddress($customerEntity->getShippingAddress());

                }

                $rslt = null;
                $fourDigits = true;

                if ( count( $rslt = explode('/', $issueDate[0] ) ) > 2 ) {
                    $issueDate = $rslt[0].'/'.$rslt[1].'/'.$rslt[2];
                    $fourDigits = strlen( $rslt[2] ) == 4;
                } elseif ( count( $rslt = explode('-', $issueDate[0] ) ) > 2 ) {
                    $issueDate = $rslt[0].'/'.$rslt[1].'/'.$rslt[2];
                    $fourDigits = strlen( $rslt[2] ) == 4;
                } else {
                    $issueDate = date('m/d/Y');
                }

                /** @var date $issueDate */
                $issueDate = \DateTime::createFromFormat('m/d/'.( $fourDigits ? 'Y' : 'y' ), $issueDate)->format('Y-m-d');
                $order->setIssueDate($issueDate);
                $order->setImported(true);
                // Se quita el item que viene por defecto
                $order->removeItem(0);

                $rowStart = 18;
                $rowEnd = 36;
                
                for($h = 0; $h < $phpExcelObject->getSheetCount(); $h++){
                	
                	// Se activa la primera hoja del libro
                	$phpExcelObject->setActiveSheetIndex($h);
                	// Se obtiene la hoja activa
                	$sheet = $phpExcelObject->getActiveSheet();
                	
                	for ( $i = $rowStart; $i <= $rowEnd; $i++ ) {
                		
                		$quantity = $sheet->rangeToArray('B'.$i)[0][0];
                		$description  = $sheet->rangeToArray('C'.$i)[0][0];
                		$unitaryCost = $sheet->rangeToArray('H'.$i)[0][0];
                		
                		$quantity = str_replace( ',', '', $quantity );
                		$unitaryCost = str_replace( ',', '', $unitaryCost );
                		
                		if ( trim($quantity) != '' && trim($description) != '' && trim($unitaryCost) != '' &&
                				$quantity != null && $description != null && $unitaryCost != null ) {
                					
                					$item = new Item();
                					$item->setQuantity((int) $quantity);
                					$item->setDescription($description);
                					$item->setUnitaryCost((float) $unitaryCost);
                					$order->addItem($item);
                					
                				}
                				
                	}
                }

                if ( count($order->getItems()) == 0 ) {
                    $order->addItem( new Item() );
                }

                $form = $this->createForm(OrderType::class, $order, [
                    'action' => $this->generateUrl('order_add'),
                ]);
            }
        }

        return array(
            'form' => $form->createView(),
            'entity' => $order,
            'currency' => $em->getRepository('SiwappConfigBundle:Property')->get('currency', 'EUR'),
        );
    }

    /**
     * @Route("/{id}/edit", name="order_edit")
     * @Template("SiwappOrderBundle:Order:edit.html.twig")
     */
    public function editAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('SiwappOrderBundle:Order')->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Order entity.');
        }
        $form = $this->createForm(OrderType::class, $entity, [
            'action' => $this->generateUrl('order_edit', ['id' => $id]),
            'editing' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $redirectRoute = 'order_edit';
            if ($request->request->has('save_draft')) {
                $entity->setStatus(Order::DRAFT);
            } elseif ($request->request->has('save_generate')) {
                $entity->setStatus(Order::APPROVED);
            }/* elseif ($entity->isDraft()) {
                $entity->setStatus(Order::PENDING);
            }*/
            // See if one of PDF/Print buttons was clicked.
            if ($request->request->has('save_pdf')) {
                $redirectRoute = 'order_show_pdf';
            } elseif ($request->request->has('save_print')) {
                $this->get('session')->set('order_auto_print', $id);
            }
            $em->persist($entity);
            $em->flush();
            $this->addTranslatedMessage('flash.updated');

            if ($request->request->has('save_close')) {
                return $this->redirect($this->generateUrl('order_index'));
            }

            // Send the email after the estimate is updated.
            if ($request->request->has('save_email')) {
                $message = $this->getEmailMessage($entity);
                $result = $this->get('mailer')->send($message);
                if ($result) {
                    $this->addTranslatedMessage('flash.emailed');
                    if (!$entity->isSentByEmail()) {
                        $entity->setSentByEmail(true);
                        $em->persist($entity);
                        $em->flush();
                    }
                }
            }
            // Generate the invoice.
            if ($request->request->has('save_generate')) {
                $invoice = $this->get('siwapp_order.estimate_generator')->generate($entity);
                if ($invoice) {
                    $this->addTranslatedMessage('flash.estimate_generated');
                }
            }

            return $this->redirect($this->generateUrl($redirectRoute, array('id' => $id)));
        }

        return array(
            'entity' => $entity,
            'form' => $form->createView(),
            'currency' => $em->getRepository('SiwappConfigBundle:Property')->get('currency', 'EUR'),
        );
    }

    /**
     * @Route("/{id}/email", name="order_email")
     * @Method({"POST"})
     */
    public function emailAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $order = $em->getRepository('SiwappOrderBundle:Order')->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Unable to find Order entity.');
        }

        $message = $this->getEmailMessage($order);
        $result = $this->get('mailer')->send($message);
        if ($result) {
            $order->setSentByEmail(true);
            $em->persist($order);
            $em->flush();
            $this->addTranslatedMessage('flash.emailed');
        }

        return $this->redirect($this->generateUrl('order_index'));
    }

    /**
     * @Route("/{id}/generate-order", name="order_generate_estimate")
     * @Method({"POST"})
     */
    public function generateInvoiceAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $order = $em->getRepository('SiwappOrderBundle:Order')->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Unable to find Order entity.');
        }

        $invoice = $this->get('siwapp_order.estimate_generator')->generate($order);
        if ($invoice) {
            $this->addTranslatedMessage('flash.estimate_generated');

            return $this->redirect($this->generateUrl('invoice_edit', ['id' => $invoice->getId()]));
        }

        return $this->redirect($this->generateUrl('estimate_index'));
    }

    /**
     * @Route("/{id}/delete", name="order_delete")
     */
    public function deleteAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $order = $em->getRepository('SiwappOrderBundle:Order')->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Unable to find Estimate entity.');
        }
        $em->remove($order);
        $em->flush();
        $this->addTranslatedMessage('flash.deleted');

        return $this->redirect($this->generateUrl('order_index'));
    }

    /**
     * @Route("/form-totals", name="order_form_totals")
     */
    public function getInvoiceFormTotals(Request $request)
    {
        $post = $request->request->get('order');
        if (!$post) {
            throw new NotFoundHttpException;
        }

        $response = $this->getInvoiceTotalsFromPost($post, new Order, $request->getLocale());

        return new JsonResponse($response);
    }

    protected function addTranslatedMessage($message, $status = 'success')
    {
        $translator = $this->get('translator');
        $this->get('session')
            ->getFlashBag()
            ->add($status, $translator->trans($message, [], 'SiwappOrderBundle'));
    }

    protected function getOrderPrintPdfHtml(Order $order, $print = false)
    {
        $settings = $this->getDoctrine()
            ->getRepository('SiwappConfigBundle:Property')
            ->getAll();

        $factor = !$print ? self::ITEMS_FACTOR : 0;

        return $this->renderView('SiwappOrderBundle:Order:print.html.twig', [
            'order'  => $order,
            'settings' => $settings,
            'print' => $print,
            'itemsxPage' => self::MAX_ITEMS_X_PAGE * ( 1 + $factor )
        ]);
    }

    protected function bulkDelete(array $orders)
    {
        $em = $this->getDoctrine()->getManager();
        foreach ($orders as $order) {
            $em->remove($order);
        }
        $em->flush();
        $this->addTranslatedMessage('flash.bulk_deleted');

        return $this->redirect($this->generateUrl('order_index'));
    }

    protected function bulkPdf(array $orders)
    {
        $pages = [];
        foreach ($orders as $order) {
            $pages[] = $this->getOrderPrintPdfHtml($order);
        }

        $html = $this->get('siwapp_core.html_page_merger')->merge($pages, '<div class="pagebreak"> </div>');
        $pdf = $this->getPdf($html);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Estimates.pdf"'
        ]);
    }

    protected function bulkPrint(array $orders)
    {
        $pages = [];
        foreach ($orders as $order) {
            $pages[] = $this->getOrderPrintPdfHtml($order, true);
        }

        $html = $this->get('siwapp_core.html_page_merger')->merge($pages, '<div class="pagebreak"> </div>');

        return new Response($html);
    }

    protected function bulkEmail(array $orders)
    {
        $em = $this->getDoctrine()->getManager();
        foreach ($orders as $order) {
            $message = $this->getEmailMessage($order);
            $result = $this->get('mailer')->send($message);
            if ($result) {
                $order->setSentByEmail(true);
                $em->persist($order);
            }
        }
        $em->flush();
        $this->addTranslatedMessage('flash.bulk_emailed');

        return $this->redirect($this->generateUrl('order_index'));
    }

    protected function getEmailMessage($order)
    {
        $em = $this->getDoctrine()->getManager();
        $configRepo = $em->getRepository('SiwappConfigBundle:Property');

        $html = $this->renderView('SiwappOrderBundle:Order:email.html.twig', array(
            'order'  => $order,
            'settings' => $em->getRepository('SiwappConfigBundle:Property')->getAll(),
            'itemsxPage' => self::MAX_ITEMS_X_PAGE * ( 999 )
        ));
        $pdf = $this->getPdf($html);
        $attachment = new \Swift_Attachment($pdf, $order->getId().'.pdf', 'application/pdf');
        $subject = '[' . $this->get('translator')->trans('order.order', [], 'SiwappOrderBundle') . ': ' . $order->label() . ']';
        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom($configRepo->get('company_email'), $configRepo->get('company_name'))
            ->setTo($order->getCustomerEmail(), $order->getCustomerName())
            ->setBody($html, 'text/html')
            ->attach($attachment);

        return $message;
    }
}
