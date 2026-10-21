<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

/** Exact, audited source-citation replacements for the supported residency rows. */
final class ResidencyAiSourceCitationReplacementGuard
{
    /** @var array<int,array<string,mixed>> */
    private const SPECS = [
        48 => [
            'year' => 1398,
            'subject' => 'oral-medicine',
            'stem_sha256' => 'dfd4905240dd30fb51a136cfd07b5e17d35652b9ba303542b985619ace326c6d',
            'choices_sha256' => '7565a5b56b2762b7af099ce54b46e00e13dc281483a55c2bf3e78b00f7cc03bc',
            'answer_sha256' => '20de10ffed0e155dc80a01a9df7a0f684f52d415e92b89e0327d75e028dcdbcf',
            'answer_choice' => 2,
            'source_id' => '01a11be7-7dff-73ac-89c0-4bb04739667c',
            'old_reference' => 'burket-oral-medicine@12e',
            'old_chapter' => '7',
            'old_legacy_page' => null,
            'old_printed_page' => null,
            'old_pdf_page' => 167,
            'old_pdf_sha256' => 'ebb1c0a98c24b21778f19e038127402a205b672ae6a31356eee722c27d199ab7',
            'old_anchor_sha256' => '772d1c52e85b5aca08c5ab834e3eec71ee80e9cba89cebb4d88cad848390987f',
            'old_confidence' => '0.400',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'burket-oral-medicine@12e',
            'chapter' => '7',
            'pdf_page' => 170,
            'printed_page' => '153',
            'pdf_sha256' => 'ebb1c0a98c24b21778f19e038127402a205b672ae6a31356eee722c27d199ab7',
            'evidence' => 'giant cell granuloma occurs either as a peripheral exophytic...be examples of benign inflammatory hyperplasia in which',
            'note' => 'Burket 12e chapter 7, printed page 153 / PDF 170, locates the giant-cell-granuloma item. The same page describes the lesion as benign inflammatory hyperplasia, conflicting with the question wording about malignant potential; preserve official choice 2 and record the key conflict.',
            'confidence' => 0.97,
        ],
        176 => [
            'year' => 1398,
            'subject' => 'operative-dentistry',
            'stem_sha256' => 'e54105c35153e6748068de6fc554b03301eac2a9eaf998638562e9fb79842c19',
            'choices_sha256' => 'd2e8d42eecdde156f43c9e5ce20c8391fc5f55bc5998011a445a10b0ee82f251',
            'answer_sha256' => '37a248c601f538c9a64324afd3a978697d801158a6671e667d6f7369b1cdddd3',
            'answer_choice' => 4,
            'source_id' => '01a11be7-7fec-7bd5-91f6-561e0e19f562',
            'old_reference' => 'sturdevant-operative@7e',
            'old_chapter' => '10',
            'old_legacy_page' => 'pdf 380',
            'old_printed_page' => null,
            'old_pdf_page' => 380,
            'old_pdf_sha256' => '4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec',
            'old_anchor_sha256' => '957cca6518c4b0a16e2b75f982876153e4e4e3ee3cb855d40ba8f9df13d3e65a',
            'old_confidence' => '0.800',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'sturdevant-operative@7e',
            'chapter' => '10',
            'pdf_page' => 331,
            'printed_page' => '318',
            'pdf_sha256' => '4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec',
            'evidence' => 'A Hollenbeck carver or base of the amalgam knife...is also useful for carving these areas',
            'note' => 'Sturdevant 7e chapter 10, printed page 318 / PDF 331, discusses removing gingival excess and the Hollenbeck carver. The official answer selects Cleoid-Discoid while the cited page names Hollenbeck; preserve official choice 4 and record the key/source conflict.',
            'confidence' => 0.97,
        ],
        180 => [
            'year' => 1398,
            'subject' => 'operative-dentistry',
            'stem_sha256' => '6eb53bca6234343d3941678870689139f769d01f6df6fa333d92b8eb54ac81c7',
            'choices_sha256' => 'e124ce7adc960e833b8aadb48fca4bcf023f2d1bcccb0525e020a6cc49c1fc99',
            'answer_sha256' => '648f2e3a66690a093ff3e429dc0b31791d6e026365cd7440f321d78fbbb6e72e',
            'answer_choice' => 4,
            'source_id' => '01a11be7-8002-76ad-9993-051f1d90bb3f',
            'old_reference' => 'sturdevant-operative@7e',
            'old_chapter' => '10',
            'old_legacy_page' => 'pdf 364',
            'old_printed_page' => null,
            'old_pdf_page' => 364,
            'old_pdf_sha256' => '4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec',
            'old_anchor_sha256' => '5d593dec2f2d00679d8a1e885ef112c454a12db062c64574d744458709b1be83',
            'old_confidence' => '0.500',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'sturdevant-operative@7e',
            'chapter' => '10',
            'pdf_page' => 360,
            'printed_page' => '347',
            'pdf_sha256' => '4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec',
            'evidence' => 'so that the proximal grooves are extended occlusally...midway between the DEJ and the enamel margin',
            'note' => 'Sturdevant 7e chapter 10, printed page 347 / PDF 360, states that short line angles extend the retention groove occlusally past the axiolinguopulpal point. This supports official choice 4; preserve the answer and replace only the citation metadata.',
            'confidence' => 0.97,
        ],
        182 => [
            'year' => 1398,
            'subject' => 'operative-dentistry',
            'stem_sha256' => 'eb8f86e66dc9c17cf1def995d8d012074865ebe86fb98e7e287998e1859d7f52',
            'choices_sha256' => 'c7ab79f0e67e8d3ea10f3ec13e7a9a40bbb2ad55573d121e1eabd69958ba5d39',
            'answer_sha256' => '767fc25018c1a161b5446d47bcf1949299c5c37a138ff4887377b9435ae73be3',
            'answer_choice' => 2,
            'source_id' => '01a11be7-800d-7472-9218-75094ed0030f',
            'old_reference' => 'sturdevant-operative@7e',
            'old_chapter' => '5',
            'old_legacy_page' => 'pdf 162',
            'old_printed_page' => null,
            'old_pdf_page' => 162,
            'old_pdf_sha256' => '4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec',
            'old_anchor_sha256' => '280f90d5bcf9541255f4aea5107bee472c843bbd0c79401781229cea884589d6',
            'old_confidence' => '0.700',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'sturdevant-operative@7e',
            'chapter' => '5',
            'pdf_page' => 169,
            'printed_page' => '156',
            'pdf_sha256' => '4ab53ca38252912464f30f165d1ab39e4f0ce907159897f9e15633254acd18ec',
            'evidence' => 'universal adhesives with chemically activated and dual-cured...varies from to 1.6 to 3.2',
            'note' => 'Sturdevant 7e chapter 5, printed page 156 / PDF 169, gives a pH range of 1.6 to 3.2 for universal adhesives and examples in the higher range. That conflicts with official choice 2 (1.0–1.5); preserve the key and record the conflict.',
            'confidence' => 0.97,
        ],
        123 => [
            'year' => 1401,
            'subject' => 'periodontics',
            'stem_sha256' => '3246d40bb005bd55cbb3d24b6c1aecf99fcc693944f49e2504bfa4fe51953c56',
            'choices_sha256' => '22569c18799a5ecee7d73008f2ba5bb7ba7519645d2e2cb1239930b8ec736054',
            'answer_sha256' => '44be6f479d6d91d5e18860ec23ed6010f3d1007bc32ba63d71d51a909d999563',
            'answer_choice' => 3,
            'source_id' => '01a11be7-99ef-7e81-9395-656bdb7daab4',
            'old_reference' => 'carranza-periodontology@13e',
            'old_chapter' => '49',
            'old_legacy_page' => null,
            'old_printed_page' => null,
            'old_pdf_page' => null,
            'old_pdf_sha256' => null,
            'old_anchor_sha256' => 'f24cc71653fc454543e034ca22197ce7d8f681944828dddd0654320be2037c6c',
            'old_confidence' => '0.800',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'carranza-periodontology@13e',
            'chapter' => '49',
            'pdf_page' => 1112,
            'printed_page' => '529',
            'pdf_sha256' => 'f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf',
            'evidence' => 'Baking soda produces its inhibition of oral malodor',
            'note' => 'Carranza 13e chapter 49, printed page 529 / PDF 1112, directly locates the baking-soda oral-malodor question. Visual reading of the original two-column page confirms that the sentence continues by relating the inhibition to bactericidal effects; the text layer interleaves that short continuation, so the machine-validated anchor preserves the exact eight-word clause. Keep the official choice 3 unchanged.',
            'confidence' => 0.900,
        ],
        128 => [
            'year' => 1401,
            'subject' => 'periodontics',
            'stem_sha256' => 'ae4cd77f66d433bcb8dd7c681bf46c820d1fd68d8b3e9b625f4a0747c087e092',
            'choices_sha256' => '6206116f63877176e7429a828d21fd81a9a4edbff4be0d3777ee2bc4a46f6db7',
            'answer_sha256' => '20c003473713b3dbf6d71838748d71aa15f174cd75b103067be28d00d192f40c',
            'answer_choice' => 1,
            'source_id' => '01a11be7-9a0a-768b-ad53-d2ad071ce941',
            'old_reference' => 'carranza-periodontology@13e',
            'old_chapter' => '35',
            'old_legacy_page' => null,
            'old_printed_page' => null,
            'old_pdf_page' => null,
            'old_pdf_sha256' => null,
            'old_anchor_sha256' => 'e614f9b6b846b278cf585e7caf4ce99314aa6322425fbdda156095b834e540ec',
            'old_confidence' => '0.350',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'carranza-periodontology@13e',
            'chapter' => '64',
            'pdf_page' => 1454,
            'printed_page' => '659.e1',
            'pdf_sha256' => 'f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf',
            'evidence' => 'The typical maxillary molar has three furcations ... which one is typically the most coronal?',
            'note' => 'Carranza 13e chapter 64, printed page 659.e1 / PDF 1454, contains the same maxillary-molar furcation question and the same answer choices as 1401 Q128, establishing the design location. The page presents the review question but does not mark its correct option. Preserve official choice 1 (mesial) and keep answer alignment explicitly unresolved.',
            'confidence' => 0.950,
        ],
        139 => [
            'year' => 1402,
            'subject' => 'periodontics',
            'stem_sha256' => '135de36f7a19697892436ec03757e00ea25a701bdbcb77af353452f204429b78',
            'choices_sha256' => '130db2fbd2cf0eef8dca766eddd20f1cd4ed24374cf82cd25083e588a1393c16',
            'answer_sha256' => 'd99140b505ae44ee35fc6950e8a72a99040471d1e38b8572f876faf5b170edb9',
            'answer_choice' => 2,
            'source_id' => '01a11b0f-a157-7fab-846d-72bde05e5622',
            'old_reference' => 'carranza-periodontology@13e',
            'old_chapter' => '34',
            'old_legacy_page' => '412.e2',
            'old_printed_page' => '412.e2',
            'old_pdf_page' => 892,
            'old_pdf_sha256' => 'f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf',
            'old_anchor_sha256' => '56c35cf8277b15fabd277c166ec5b2357581ba3cbe8d4d2c5fdac867b4fd82a8',
            'old_confidence' => '0.900',
            'old_official_scope_status' => 'outside_scope_exam_question',
            'old_official_scope_note_sha256' => 'af20fd1be878f445b582946e3a4db84be3208de64e083d5c419cf5ae3db7cfe5',
            'reference' => 'carranza-periodontology@13e',
            'chapter' => '28',
            'pdf_page' => 785,
            'printed_page' => '353',
            'pdf_sha256' => 'f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf',
            'evidence' => 'Familiar aggregation of aggressive peri- ... advisable to verify similar cases in the family',
            'note' => 'Carranza 13e chapter 28, printed page 353 / PDF 785, describes familial aggregation and recommends verifying similar family cases. The page does not specify first-degree relatives; preserve official final choice 2 and record that qualifier as an inference.',
            'confidence' => 0.900,
        ],
        165 => [
            'year' => 1401,
            'subject' => 'operative-dentistry',
            'stem_sha256' => '21365d473541477aac5b6b871fd2d75bdac4e3b970812cd5b5f7561c75752a64',
            'choices_sha256' => '1d56e451c8d02c428619ef24b95e93e20e5f9956bb28929d695f437b57c0e343',
            'answer_sha256' => 'f3f070afa8ad5df580cdec18672f8a8a762ce4a246ea20368622cf59b1a15794',
            'answer_choice' => 3,
            'source_id' => '01a11be7-9b0a-7a10-ae22-ebb6236b36cc',
            'old_reference' => 'summit-operative@4e',
            'old_chapter' => '9',
            'old_legacy_page' => 'pdf 476',
            'old_printed_page' => null,
            'old_pdf_page' => 476,
            'old_pdf_sha256' => 'a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6',
            'old_anchor_sha256' => 'f48bf520cf951de997509393be0b22f9e6f063952f1ba395a69a591196777292',
            'old_confidence' => '0.800',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'craig-restorative-materials@14e',
            'chapter' => '13',
            'pdf_page' => 290,
            'printed_page' => '279',
            'pdf_sha256' => '333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9',
            'evidence' => 'With these systems, the smear layer is dis- ... solved and incorporated into the hybrid layer',
            'note' => 'Craig 14e chapter 13, printed page 279 / PDF 290, says self-etch systems dissolve and incorporate the smear layer. This supports the self-etch mechanism but does not distinguish two-step from one-step self-etch; preserve official choice 3 and record the specificity conflict.',
            'confidence' => 0.900,
        ],
        168 => [
            'year' => 1401,
            'subject' => 'operative-dentistry',
            'stem_sha256' => '9894149f16b8c4f5160b22b131066fe0b1488f1bc5f52116e6159832c9b9926b',
            'choices_sha256' => '447a03a20ec9ba71dcd5f7d09b4b537256cb97cda1beecbddbc7864a8d74041a',
            'answer_sha256' => '23b0f883c3f687ed853c0b4f115570b444e98a796af732fd08e8095a8ad28215',
            'answer_choice' => 3,
            'source_id' => '01a11be7-9b21-7284-8862-7a777020edd6',
            'old_reference' => 'summit-operative@4e',
            'old_chapter' => '9',
            'old_legacy_page' => 'pdf 438',
            'old_printed_page' => null,
            'old_pdf_page' => 438,
            'old_pdf_sha256' => 'a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6',
            'old_anchor_sha256' => 'd6cf18397643df3dcedd11a7b0a45be993777ce73566556aeb0fb1b40058ce19',
            'old_confidence' => '0.900',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'craig-restorative-materials@14e',
            'chapter' => '13',
            'pdf_page' => 290,
            'printed_page' => '279',
            'pdf_sha256' => '333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9',
            'evidence' => 'Self-etch systems have the great advantages of ... initial acidity and high water content',
            'note' => 'Craig 14e chapter 13, printed page 279 / PDF 290, identifies high water content as an advantage of self-etch systems. Preserve official final choice 3.',
            'confidence' => 0.950,
        ],
        175 => [
            'year' => 1402,
            'subject' => 'operative-dentistry',
            'stem_sha256' => '384991e96437bcb328e44fff014a07a5d58ba0f14dc4ae1e780893e525f00311',
            'choices_sha256' => '59d80234cac471fe5445295af65a11a5c3b44a0b4e33b4ded1ca7a4bfc3bf71f',
            'answer_sha256' => '955c6627f3adfbcf8f1e9b39108431a0d82c90e44157f5eb0289b74b29bde510',
            'answer_choice' => 3,
            'source_id' => '01a11b0f-a231-7abd-bac7-84996734e589',
            'old_reference' => 'summit-operative@4e',
            'old_chapter' => '9',
            'old_legacy_page' => '2',
            'old_printed_page' => '2',
            'old_pdf_page' => null,
            'old_pdf_sha256' => null,
            'old_anchor_sha256' => 'c7b72d1152c945076e492fd723a816bf2069cb3af670ab418712b5fb30d323ef',
            'old_confidence' => '0.850',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'craig-restorative-materials@14e',
            'chapter' => '13',
            'pdf_page' => 288,
            'printed_page' => '277',
            'pdf_sha256' => '333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9',
            'evidence' => 'three-step etch-and-rinse systems as a group remain the “gold standard” for adhesive systems, in both laboratory and clinical evaluations',
            'note' => 'Craig 14e chapter 13, printed page 277 / PDF 288, directly identifies three-step etch-and-rinse systems as the gold standard. Preserve official final choice 3.',
            'confidence' => 0.95,
        ],
        190 => [
            'year' => 1402,
            'subject' => 'operative-dentistry',
            'stem_sha256' => '879d051bc4e280d5311b0f014f83adbac121d3b7669e06929b3e38dea51f2fa7',
            'choices_sha256' => 'e00b9b3f3e6c3b896bede6aa41d79c405d4fc82cb65f0693099653456649f417',
            'answer_sha256' => 'bad5084d87dd35adc3c2fb945dee8e5c72be5703361b221daa63b7cfdb27b4fb',
            'answer_choice' => 3,
            'source_id' => '01a11b0f-a28c-7da3-8039-d054645e11c3',
            'old_reference' => 'summit-operative@4e',
            'old_chapter' => '9',
            'old_legacy_page' => 'pdf 450',
            'old_printed_page' => null,
            'old_pdf_page' => 450,
            'old_pdf_sha256' => 'a634d3519e67001151d0d406c3fb91dbeea30ff546c92ed3dec8dfade5a3d6f6',
            'old_anchor_sha256' => 'c5919929151a7f59626d5af7c16f3b3972ed8b960f9cfb376f17347a878fa9f6',
            'old_confidence' => '0.600',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'craig-restorative-materials@14e',
            'chapter' => '13',
            'pdf_page' => 288,
            'printed_page' => '277',
            'pdf_sha256' => '333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9',
            'evidence' => 'contains some prismless enamel that has not yet worn away and requires longer etching times (20 to 30 seconds)',
            'note' => 'Craig 14e chapter 13, printed page 277 / PDF 288, directly gives 20 to 30 seconds for deciduous unground enamel. Preserve official final choice 3.',
            'confidence' => 0.95,
        ],
        244 => [
            'year' => 1402,
            'subject' => 'dental-materials',
            'stem_sha256' => 'c696829eb9e906464a9dd460514ac6885c44be0b87d89751b8b360598c62d20b',
            'choices_sha256' => '4d88ce4708f3a9b0596728b389e84e5b0b19fc2f31295193a5b8c0932814ab61',
            'answer_sha256' => '18eb8874bb78dba77c2dd6b91ce112e085c364f8c52e942cc6fdb4257e30050f',
            'answer_choice' => 3,
            'source_id' => '01a11b0f-a346-780b-a249-8db4bb80bd14',
            'old_reference' => 'van-noort-materials@4e',
            'old_chapter' => '2.2',
            'old_legacy_page' => '74',
            'old_printed_page' => '74',
            'old_pdf_page' => null,
            'old_pdf_sha256' => null,
            'old_anchor_sha256' => '15239a7d2b0e69b3b1168c1c95335ac268a1ba7b0bbf825bca88bc95f667cdad',
            'old_confidence' => '0.400',
            'old_official_scope_status' => 'in_scope',
            'old_official_scope_note_sha256' => null,
            'reference' => 'powers-wataha-materials@11e',
            'chapter' => '4',
            'pdf_page' => 67,
            'printed_page' => '49',
            'pdf_sha256' => 'df1b471c7c2949422cbe9f883a792800cb811e39c7217dbae781084df8478399',
            'evidence' => 'particles with a filler loading of 66% to 70% by volume',
            'note' => 'Powers and Wataha 11e chapter 4, printed page 49 / PDF 67, says 66–70% by volume; the official option says by weight. That unit difference supports the keyed “not correct” option 3. Preserve the official final key.',
            'confidence' => 0.95,
        ],
    ];

    /** @return array<string,mixed>|null */
    public static function spec(int $number): ?array
    {
        return self::SPECS[$number] ?? null;
    }

    /** @return list<int> */
    public static function supportedQuestions(?int $year = null): array
    {
        if ($year === null) {
            return array_keys(self::SPECS);
        }
        return array_keys(array_filter(self::SPECS,static fn(array $spec):bool=>$spec['year']===$year));
    }

    public static function modeArgumentsValid(bool $apply, bool $hasBackup, bool $hasReceipt, bool $hasPreview): bool
    {
        return $apply
            ? $hasBackup && $hasReceipt && $hasPreview
            : !$hasBackup && !$hasReceipt;
    }

    /** @param array<int|string,array<string,mixed>> $before @param array<int|string,array<string,mixed>> $after */
    public static function immutableSnapshotsMatch(array $before, array $after): bool
    {
        return $before !== [] && $before === $after;
    }

    /** @param array<string,mixed> $plan */
    public static function planMatches(array $plan): bool
    {
        if (($plan['format'] ?? null) !== 'fanoos.residency-ai-source-citation-replacement-plan/1'
            || !in_array($plan['year'] ?? null,[1398,1401,1402],true) || ($plan['round'] ?? null) !== 1
            || !is_string($plan['audit_path'] ?? null)
            || !preg_match('~^/srv/fanoos/shared/research/classification/recovery-(?:1398|1401|1402)-[a-z0-9-]+/classification/reports/[^/]+\.json$~D', $plan['audit_path'])
            || !self::sha($plan['audit_sha256'] ?? null)
            || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
            || $plan['entries'] === [] || count($plan['entries']) > count(self::SPECS)) {
            return false;
        }
        $numbers=array_column($plan['entries'],'number');
        $supportedInPlan=array_values(array_filter(self::supportedQuestions((int)$plan['year']),static fn(int $number):bool=>in_array($number,$numbers,true)));
        if($numbers!==$supportedInPlan||count(array_unique($numbers))!==count($numbers))return false;

        foreach ($plan['entries'] as $entry) {
            if (!is_array($entry) || !is_int($entry['number'] ?? null)) {
                return false;
            }
            $spec = self::spec($entry['number']);
            if ($spec === null || $spec['year'] !== $plan['year']) {
                return false;
            }
            foreach (['year','subject','stem_sha256','choices_sha256','answer_sha256','source_id','old_reference',
                'old_chapter','old_legacy_page','old_printed_page','old_pdf_page','old_pdf_sha256',
                'old_anchor_sha256','old_confidence','old_official_scope_status','old_official_scope_note_sha256','answer_choice',
                'reference','chapter','pdf_page','printed_page','pdf_sha256','evidence','note','confidence'] as $key) {
                if (!array_key_exists($key, $entry) || $entry[$key] !== $spec[$key]) {
                    return false;
                }
            }
            if (!in_array($entry['confidence'] ?? null,[0.90,0.95,0.97],true)
                || !is_string($entry['audit_batch'] ?? null)
                || !in_array($entry['audit_batch'], [
                    '1398:operative-dentistry:operative-low-confidence',
                    '1398:oral-medicine:oral-medicine-low-confidence',
                    '1401:periodontics:q123-q128',
                    '1401:operative-dentistry:q165-q168',
                    '1402:periodontics:q139',
                    '1402:operative-dentistry:q175-q190',
                    '1402:dental-materials:q244',
                ], true)
                || ($entry['old_scope_unverified'] ?? null) !== false) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $audit @param array<string,mixed> $plan */
    public static function researchMatches(array $audit, array $plan): bool
    {
        if (($audit['format'] ?? null) !== 'fanoos.classification.provenance-audit/1'
            || ($audit['research_only'] ?? null) !== true
            || !is_array($audit['batches'] ?? null)
            || ($audit['questions'] ?? null) !== count($plan['entries'])
            || ($audit['accepted'] ?? null) !== count($plan['entries']) || ($audit['pending'] ?? null) !== 0) {
            return false;
        }
        $batches = [];
        foreach ($audit['batches'] as $batch) {
            if (is_array($batch) && is_string($batch['batch'] ?? null)) {
                $batches[$batch['batch']] = $batch;
            }
        }
        $expectedBatchNumbers=[];
        foreach($plan['entries'] as $entry){$expectedBatchNumbers[$entry['audit_batch']][]=(string)$entry['number'];}
        if(($audit['batch_count']??null)!==count($expectedBatchNumbers)
            ||count($audit['batches'])!==count($expectedBatchNumbers))return false;
        foreach ($expectedBatchNumbers as $name => $numbers) {
            $batch = $batches[$name] ?? null;
            if (!is_array($batch) || ($batch['questions'] ?? null) !== count($numbers)
                || ($batch['accepted'] ?? null) !== count($numbers) || ($batch['pending'] ?? null) !== 0
                || !is_array($batch['mapped'] ?? null)
                || array_map('strval',array_keys($batch['mapped'])) !== $numbers) {
                return false;
            }
        }
        foreach ($plan['entries'] as $entry) {
            $batch = $batches[$entry['audit_batch']] ?? null;
            $question = (string) $entry['number'];
            $mapped = is_array($batch) ? ($batch['mapped'][$question] ?? null) : null;
            $pdf = is_array($batch)
                ? ($batch['source_pdf_provenance'][$entry['reference']] ?? null) : null;
            if (!is_array($batch) || ($batch['exam_year'] ?? null) !== ($plan['year'] ?? null)
                || ($batch['subject'] ?? null) !== $entry['subject']
                || ($batch['question_content_identical'] ?? null) !== true
                || !is_array($mapped)
                || ($mapped['ref'] ?? null) !== $entry['reference'] . '#ch' . str_pad($entry['chapter'], 2, '0', STR_PAD_LEFT)
                || ($mapped['pdf_page'] ?? null) !== $entry['pdf_page']
                || self::mappedConfidenceMismatch($mapped, (float) $entry['confidence'])
                || !is_array($pdf) || ($pdf['sha256'] ?? null) !== $entry['pdf_sha256']) {
                return false;
            }
        }
        return true;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }

    /** @param array<string,mixed> $mapped */
    private static function mappedConfidenceMismatch(array $mapped, float $expected): bool
    {
        foreach ([
            'confidence_source' => $expected,
            'confidence_node' => $expected,
            'confidence_page' => $expected,
        ] as $field => $value) {
            if (!is_numeric($mapped[$field] ?? null)
                || abs((float) $mapped[$field] - $value) > 0.0005) {
                return true;
            }
        }
        return false;
    }
}
