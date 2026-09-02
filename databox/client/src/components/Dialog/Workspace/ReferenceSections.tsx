import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import CodeEditor from '../../Form/CodeEditor/CodeEditor.tsx';
import {ReferenceSection} from '../../../types';

type Props = {
    sections: ReferenceSection[];
};

export default function ReferenceSections({sections}: Props) {
    return (
        <div>
            {sections.map(section => (
                <Accordion
                    key={section.name}
                    slotProps={{
                        transition: {
                            unmountOnExit: true,
                        },
                    }}
                >
                    <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                        <div>
                            <Typography variant={'subtitle2'}>
                                {section.name}
                            </Typography>
                            {section.description ? (
                                <Typography
                                    variant={'body2'}
                                    color={'text.secondary'}
                                >
                                    {section.description}
                                </Typography>
                            ) : null}
                        </div>
                    </AccordionSummary>
                    <AccordionDetails>
                        <CodeEditor
                            mode={'yaml'}
                            theme={'github'}
                            height={'300px'}
                            value={section.reference}
                            readOnly={true}
                        />
                    </AccordionDetails>
                </Accordion>
            ))}
        </div>
    );
}
