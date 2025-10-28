<?php
namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Services\Tickets\Importer\MigratorService;
use FluentSupport\Framework\Request\Request;
use FluentSupport\App\Services\Tickets\Importer\BaseImporter;


class TicketImportController extends Controller
{
    public function getStats ( MigratorService $importService )
    {
        $stats = $importService->getStats();
        if(!$stats) {
            return [];
        }
        return $stats;
    }

    public function importTickets(MigratorService $importService, Request $request)
    {
        try {
            return $importService->handleImport( $request->getSafe('page', 'intval'), $request->getSafe('handler'), $request->getSafe('query', []) );
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function deleteTickets (MigratorService $importService, Request $request)
    {
        return $importService->deleteTickets($request->getSafe('page', 'intval'), $request->getSafe('handler'));
    }
}
