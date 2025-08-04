<?php

namespace App\Controller;

use App\Utils\Registry;
use App\Utils\Utility;
use Exception;
use Pimcore\Controller\FrontendController;
use Pimcore\Db;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\GroupProduct;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\Element\DuplicateFullPathException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;
use App\Service\StickerService;

class StickerController extends FrontendController
{
    private StickerService $stickerService;

    public function __construct(StickerService $stickerService)
    {
        $this->stickerService = $stickerService;
    }

    protected function getGroupList(): array
    {
        $gproduct = new GroupProduct\Listing();
        $result = $gproduct->load();
        $groups = [];
        foreach ($result as $item) {
            $groups[] = [
                'name' => $item->getKey(),
                'id' => $item->getId()
            ];
        }
        return $groups;
    }

    /**
     * @Route("/sticker", name="sticker_main_page")
     * @return Response
     */
    public function stickerMainPage(): Response
    {
        return $this->render('sticker/sticker.html.twig', [
            'groups' => $this->getGroupList()
        ]);
    }

    /**
     * @Route("/sticker/add-sticker-group", name="sticker_new_group", methods={"POST"})
     * @param Request $request
     * @return Response
     * @throws DuplicateFullPathException
     */
    public function addStickerGroup(Request $request): Response
    {
        $formData = $request->request->get('form_data');
        if (!preg_match('/^[a-zA-Z0-9_ ]+$/', $formData)) {
            $this->addFlash('error', 'Grup adı sadece harf, rakam, boşluk ve alt çizgi içerebilir.');
            return $this->redirectToRoute('sticker_main_page');
        }
        if (mb_strlen($formData) > 190) {
            $this->addFlash('error', 'Grup adı 190 karakterden uzun olamaz.');
            return $this->redirectToRoute('sticker_main_page');
        }
        $operationFolder = Utility::checkSetPath('Operasyonlar');
        if (!$operationFolder) {
            $this->addFlash('error', 'Operasyonlar klasörü bulunamadı.');
            return $this->redirectToRoute('sticker_main_page');
        }
        $existingGroup = GroupProduct::getByPath($operationFolder->getFullPath() . '/' . $formData);
        if ($existingGroup) {
            $this->addFlash('error', 'Bu grup zaten mevcut.');
            return $this->redirectToRoute('sticker_main_page');
        }
        $newGroup = new GroupProduct();
        $newGroup->setParent($operationFolder);
        $newGroup->setKey($formData);
        $newGroup->setPublished(true);
        try {
            $newGroup->save();
        } catch (Exception $e) {
            $this->addFlash('error', 'Grup eklenirken bir hata oluştu:'.' '.$e);
            return $this->redirectToRoute('sticker_main_page');
        }
        $this->addFlash('success', 'Grup Başarıyla Eklendi.');
        return $this->redirectToRoute('sticker_main_page');
    }

    /**
     * @Route("/sticker/get-stickers/{groupId}", name="get_stickers", methods={"GET"})
     */
    public function getStickers(int $groupId, Request $request): JsonResponse
    {
        try {
            error_log("StickerController: Getting stickers for group {$groupId}");
            $params = [
                'page' => $request->query->getInt('page', 1),
                'limit' => $request->query->getInt('limit', 5),
                'searchTerm' => $request->query->get('searchTerm')
            ];
            error_log("StickerController: Params - " . json_encode($params));
            $result = $this->stickerService->getGroupStickers($groupId, $params);
            error_log("StickerController: Service result - " . json_encode($result));
            return new JsonResponse([
                'success' => true,
                ...$result
            ]);
        } catch (Exception $e) {
            error_log("StickerController Error: " . $e->getMessage());
            error_log("StickerController Error Trace: " . $e->getTraceAsString());
            
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ürünün sticker durumunu kontrol et
     */
    private function checkStickerStatus(Product $product): array
    {
        $status = [
            'has_eu_sticker' => false,
            'has_iwasku_sticker' => false,
            'eu_sticker_count' => 0,
            'iwasku_sticker_count' => 0,
            'eu_sticker_links' => [],
            'iwasku_sticker_links' => []
        ];
        
        // EU Sticker kontrolü
        try {
            $euStickers = $product->getInheritedField('sticker4x6eu');
            if ($euStickers) {
                if (is_array($euStickers)) {
                    $status['has_eu_sticker'] = count($euStickers) > 0;
                    $status['eu_sticker_count'] = count($euStickers);
                    foreach ($euStickers as $sticker) {
                        if ($sticker instanceof Asset) {
                            $status['eu_sticker_links'][] = $sticker->getFullPath();
                        }
                    }
                } else if ($euStickers instanceof Asset) {
                    $status['has_eu_sticker'] = true;
                    $status['eu_sticker_count'] = 1;
                    $status['eu_sticker_links'][] = $euStickers->getFullPath();
                }
            }
        } catch (Exception $e) {
            error_log("EU sticker check failed for product {$product->getIwasku()}: " . $e->getMessage());
        }
        
        // IWASKU Sticker kontrolü
        try {
            $iwaskuStickers = $product->getInheritedField('sticker4x6iwasku');
            if ($iwaskuStickers) {
                if (is_array($iwaskuStickers)) {
                    $status['has_iwasku_sticker'] = count($iwaskuStickers) > 0;
                    $status['iwasku_sticker_count'] = count($iwaskuStickers);
                    foreach ($iwaskuStickers as $sticker) {
                        if ($sticker instanceof Asset) {
                            $status['iwasku_sticker_links'][] = $sticker->getFullPath();
                        }
                    }
                } else if ($iwaskuStickers instanceof Asset) {
                    $status['has_iwasku_sticker'] = true;
                    $status['iwasku_sticker_count'] = 1;
                    $status['iwasku_sticker_links'][] = $iwaskuStickers->getFullPath();
                }
            }
        } catch (Exception $e) {
            error_log("IWASKU sticker check failed for product {$product->getIwasku()}: " . $e->getMessage());
        }
        
        return $status;
    }

    /**
     * @Route("/sticker/get-product-details/{productIdentifier}/{groupId}", name="get_product_details", methods={"GET"})
     */
    public function getProductDetails($productIdentifier, $groupId): JsonResponse
    {
        try {
            $result = $this->stickerService->getProductDetails($productIdentifier, $groupId);
            return new JsonResponse($result);
            
        } catch (Exception $e) {
            error_log("GetProductDetails Controller Error: " . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * @Route("/sticker/add-sticker", name="sticker_new", methods={"POST"})
     */
    public function addSticker(Request $request): Response
    {
        try {
            $productId = $request->request->get('form_data');
            $groupId = $request->request->get('group_id');
            $result = $this->stickerService->addStickerToGroup($productId, $groupId);
            if ($result['success']) {
                $this->addFlash('success', $result['message']);
            } else {
                $this->addFlash('error', $result['message']);
            }
        } catch (Exception $e) {
            error_log("AddSticker Controller Error: " . $e->getMessage());
            $this->addFlash('error', 'Beklenmeyen bir hata oluştu.');
        }
        return $this->redirectToRoute('sticker_main_page');
    }

}