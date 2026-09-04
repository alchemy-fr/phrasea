import {AlertColor} from '@mui/material';
import {TFunction} from '@alchemy/i18n';
import {ApiFile} from '../../../../types.ts';
import {FileAnalysisState} from './analysisTypes.ts';

/**
 * How one file's analysis should be presented, derived from the two orthogonal
 * axes the API exposes: `analysisState` (what the analysis produced) and
 * `analysisEnforced` (whether the workspace blocks on that result).
 *
 * Both the chip shown on thumbnails and the file Info tab read from here, so a
 * given state always reads the same way.
 */
export type FileAnalysisPresentation = {
    severity: AlertColor;
    // Short wording, for the chip.
    label: string;
    // Full sentence, for the Info tab alert.
    message: string;
    // Whether the state is worth flagging on thumbnails and attachment rows.
    showChip: boolean;
    // Whether `message` says more than `label` already does, and so deserves an
    // alert of its own next to the chip.
    showAlert: boolean;
    // Whether to unfold the per-analyzer report below the alert.
    showReport: boolean;
};

export function getFileAnalysisPresentation(
    file: ApiFile,
    t: TFunction
): FileAnalysisPresentation {
    const hasResults = (file.analysis?.results ?? []).length > 0;

    switch (file.analysisState) {
        case FileAnalysisState.Passed:
            return {
                severity: 'success',
                label: t('file.analysis.state.passed.label', 'Analyzed'),
                message: t(
                    'file.analysis.state.passed.message',
                    'This file was analyzed successfully.'
                ),
                showChip: false,
                showAlert: false,
                showReport: hasResults,
            };

        case FileAnalysisState.Failed:
            // When the workspace does not require the analysis, the file is not
            // blocked: report the findings, but do not call it a rejection.
            return file.analysisEnforced
                ? {
                      severity: 'error',
                      label: t('file.rejected', 'Rejected'),
                      message: hasResults
                          ? t(
                                'file_analyzer.result.status.rejected_with_reason',
                                'This file was rejected by the following analyzers.'
                            )
                          : t(
                                'file_analyzer.result.status.rejected',
                                'This file was rejected by the analysis.'
                            ),
                      showChip: true,
                      showAlert: true,
                      showReport: hasResults,
                  }
                : {
                      severity: 'warning',
                      label: t(
                          'file.analysis.state.reported.label',
                          'Findings'
                      ),
                      message: t(
                          'file.analysis.state.reported.message',
                          'The analysis reported issues, but it is not required on this workspace: this file is not blocked.'
                      ),
                      showChip: true,
                      showAlert: true,
                      showReport: hasResults,
                  };

        case FileAnalysisState.Bypassed:
            return {
                severity: 'warning',
                label: t('file.analysis.state.bypassed.label', 'Bypassed'),
                message: t(
                    'file.analysis.state.bypassed.message',
                    'The analysis was manually bypassed by an administrator.'
                ),
                showChip: true,
                showAlert: true,
                showReport: hasResults,
            };

        case FileAnalysisState.Skipped:
            return {
                severity: 'info',
                label: t('file.analysis.state.skipped.label', 'Skipped'),
                message:
                    file.analysis?.message ??
                    t(
                        'file_analyzer.result.status.skipped',
                        'File analysis was skipped.'
                    ),
                showChip: false,
                showAlert: true,
                showReport: hasResults,
            };

        case FileAnalysisState.NotApplicable:
            return {
                severity: 'info',
                label: t(
                    'file.analysis.state.not_applicable.label',
                    'Not applicable'
                ),
                message: t(
                    'file.analysis.state.not_applicable.message',
                    'This file does not need to be analyzed.'
                ),
                showChip: false,
                showAlert: false,
                showReport: false,
            };

        case FileAnalysisState.NotAnalyzed:
        default:
            // Only "pending" while the workspace actually expects a result.
            return file.analysisEnforced
                ? {
                      severity: 'info',
                      label: t(
                          'file.analysis_pending',
                          'Analysis in progress…'
                      ),
                      message: t(
                          'file.analysis.state.pending.message',
                          'This file is waiting to be analyzed.'
                      ),
                      showChip: true,
                      showAlert: false,
                      showReport: false,
                  }
                : {
                      severity: 'info',
                      label: t(
                          'file.analysis.state.not_required.label',
                          'Not required'
                      ),
                      message: t(
                          'file.analysis.state.not_required.message',
                          'File analysis is not required on this workspace.'
                      ),
                      showChip: false,
                      showAlert: false,
                      showReport: false,
                  };
    }
}
