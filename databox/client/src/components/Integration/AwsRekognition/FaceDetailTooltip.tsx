import {ReactNode} from 'react';
import {FaceDetail, TValueConfidence} from './types';
import {Chip} from '@mui/material';
import ValueConfidence from './ValueConfidence';
import {useTranslation} from 'react-i18next';

type Props = {
    detail: FaceDetail;
    title: string;
};

function BooleanAssertion({
    title,
    value,
}: {
    title: string;
    value: TValueConfidence<boolean> | undefined;
}) {
    const {t} = useTranslation();
    if (!value) {
        return null;
    }

    return (
        <Assertion
            title={title}
            value={
                <>
                    {value.Value ? (
                        <Chip
                            size={'small'}
                            color={'success'}
                            label={t('common.yes', `Yes`)}
                        />
                    ) : (
                        <Chip
                            size={'small'}
                            color={'error'}
                            label={t('common.no', `No`)}
                        />
                    )}{' '}
                    (<ValueConfidence confidence={value.Confidence} />)
                </>
            }
        />
    );
}

function Assertion({title, value}: {title: string; value: ReactNode}) {
    return (
        <div>
            <b>{title}</b>: {value}
        </div>
    );
}

export default function FaceDetailTooltip({detail, title}: Props) {
    const {t} = useTranslation();
    return (
        <>
            <div>
                <b>{title}</b>{' '}
                <small>
                    (<ValueConfidence confidence={detail.Confidence} />)
                </small>
            </div>
            {detail.AgeRange && (
                <Assertion
                    title={t('face_detail_tooltip.age_range', `Age range`)}
                    value={`${detail.AgeRange.Low} - ${detail.AgeRange.High} years old`}
                />
            )}
            {detail.Gender && (
                <Assertion
                    title={t('face_detail_tooltip.gender', `Gender`)}
                    value={
                        <>
                            {detail.Gender.Value}
                            {' - '}
                            <ValueConfidence
                                confidence={detail.Gender.Confidence}
                            />
                        </>
                    }
                />
            )}
            <BooleanAssertion
                title={t('face_detail_tooltip.has_a_beard', `Has a beard`)}
                value={detail.Beard}
            />
            <BooleanAssertion
                title={t('face_detail_tooltip.smiling', `Smiling`)}
                value={detail.Smile}
            />
            <BooleanAssertion
                title={t('face_detail_tooltip.eyes_open', `Eyes Open`)}
                value={detail.EyesOpen}
            />
            <BooleanAssertion
                title={t('face_detail_tooltip.eyeglasses', `Eyeglasses`)}
                value={detail.Eyeglasses}
            />
            <BooleanAssertion
                title={t('face_detail_tooltip.mouth_open', `Mouth Open`)}
                value={detail.MouthOpen}
            />
            <BooleanAssertion
                title={t('face_detail_tooltip.mustache', `Mustache`)}
                value={detail.Mustache}
            />
        </>
    );
}
