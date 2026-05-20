import React, {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {AttributeEntity} from '../../../types.ts';
import {ReloadFunc} from '../../AssetList/types.ts';
import {mergeAttributeEntities} from '../../../api/attributeEntity.ts';
import FormDialog from '../FormDialog.tsx';
import CallMergeIcon from '@mui/icons-material/CallMerge';
import {List, ListItem, ListItemButton, Typography} from '@mui/material';
import AttributeEntityListText from '../../Media/Asset/Attribute/AttributeEntityListText.tsx';

type Props = {
    items: AttributeEntity[];
    reload: ReloadFunc;
} & StackedModalProps;

export default function MergeEntitiesDialog({
    items,
    reload,
    ...modalProps
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const [loading, setLoading] = useState(false);
    const [kept, setKept] = useState<string>(items[0]?.id);

    return (
        <FormDialog
            maxWidth={'sm'}
            {...modalProps}
            loading={loading}
            title={t(
                'attribute_entity.batch_merge.dialog.title',
                'Merge entities'
            )}
            onSave={async () => {
                setLoading(true);
                try {
                    await mergeAttributeEntities(
                        kept,
                        items.map(i => i.id)
                    );
                    await reload();
                    closeModal();
                } finally {
                    setLoading(false);
                }
            }}
            submitIcon={<CallMergeIcon />}
            submitLabel={t(
                'attribute_entity.batch_merge.dialog.submit',
                'Merge'
            )}
        >
            <Typography sx={{mb: 2}}>
                {t(
                    'attribute_entity.batch_merge.dialog.keep',
                    'Select the entity you want to keep. The others will be merged into it and deleted.'
                )}
            </Typography>
            <List>
                {items.map(i => (
                    <ListItem key={i.id} disablePadding={true}>
                        <ListItemButton
                            selected={kept === i.id}
                            onClick={() => setKept(i.id)}
                        >
                            <AttributeEntityListText
                                data={i}
                                inList={true}
                                options={{
                                    noTranslate: true,
                                }}
                            />
                        </ListItemButton>
                    </ListItem>
                ))}
            </List>
        </FormDialog>
    );
}
