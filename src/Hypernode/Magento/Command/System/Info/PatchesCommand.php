<?php

namespace Hypernode\Magento\Command\System\Info;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

class PatchesCommand extends AbstractMagentoCommand
{
    const HYPERNODE_PATCH_TOOL_URL = 'http://tools.hypernode.com/patches/';

    private $patchFile;
    private $appliedPatches;

    protected function configure()
    {
        $this
            ->setName('sys:info:patches')
            ->setDescription('Determine required patches [Hypernode]');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {
            $this->patchFile = \Mage::getBaseDir('etc') . DS . 'applied.patches.list';

            $_isEnterprise = $this->getApplication()->isMagentoEnterprise();

            $_patchUrl = self::HYPERNODE_PATCH_TOOL_URL . ($_isEnterprise ? 'enterprise' : 'community') . DS . \Mage::getVersion();

            try {
                $curl = curl_init($_patchUrl);
                curl_setopt($curl, CURLOPT_HEADER, false);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

                $patchesListJson = curl_exec($curl);
            } catch (Exception $e) {
                $output->writeln('<error>Could not fetch data from Hypernode platform; ' . $e->getMessage() . '</error>');
                exit(1);
            }

            $patchesList = json_decode($patchesListJson, true);
            if (!$patchesList) {
                $output->writeln('<error>Could not fetch patches list from Hypernode platform.</error>');
                exit(1);
            }

            if (file_exists($this->patchFile)) {
                $this->_loadPatchFile();
            }

            if(count($patchesList, COUNT_RECURSIVE) - count($patchesList)) {
                $table = new Table($output);
                $table->setHeaders(array('Patch', 'Type', 'Applied'));
                $rows = array();
                foreach ($patchesList as $patchType => $patches) {
                    foreach ($patches as $patch) {
                        $rows[] = array(
                            $patch,
                            $patchType,
                            (isset($this->appliedPatches[$patch]) ? '<info>Yes</info>' : (($patchType == 'required') ? '<error>No</error>' : '<notice>No</notice>'))
                        );
                    }
                }

                $table->setRows($rows);
                $table->render();
            } else {
                $output->writeln('<info>No patches are necessary, your installation is up to date!</info>');
            }
        }
    }

    /**
     * Use to load the patches array with applied patches.
     *
     * @return void
     *
     * Thanks @philwinkle - https://github.com/philwinkle/Philwinkle_AppliedPatches/
     */
    protected function _loadPatchFile()
    {
        $ioAdapter = new \Varien_Io_File();
        if (!$ioAdapter->fileExists($this->patchFile)) {
            return;
        }
        $ioAdapter->open(array('path' => $ioAdapter->dirname($this->patchFile)));
        $ioAdapter->streamOpen($this->patchFile, 'r');
        while ($buffer = $ioAdapter->streamRead()) {
            if (stristr($buffer, '|')) {
                list($date, $patch) = array_map('trim', explode('|', $buffer));
                $this->appliedPatches[$patch] = true;
            }
        }
        $ioAdapter->streamClose();
    }

}