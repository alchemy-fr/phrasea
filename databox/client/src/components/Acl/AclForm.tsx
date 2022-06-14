import React, {useCallback, useEffect, useState} from 'react';
import AceRow from "./AceRow";
import {Ace, Group, User, UserType} from "../../types";
import {deleteAce, getAces, putAce} from "../../api/acl";
import {getGroups, getUsers} from "../../api/user";
import UserSelect from "../User/UserSelect";
import GroupSelect from "../User/GroupSelect";
import {Box, Button, Grid, Skeleton} from "@mui/material";
import FormRow from "../Form/FormRow";
import {useTranslation} from 'react-i18next';

export const aclPermissions: { [key: string]: number } = {
    VIEW: 1,
    SHARE: 256,
    CREATE: 2,
    EDIT: 4,
    DELETE: 8,
    UNDELETE: 16,
    OPERATOR: 32,
    MASTER: 64,
    OWNER: 128,
}

type Props = {
    objectType: "collection" | "asset" | "workspace";
    objectId: string;
};

type State = {
    aces: Ace[];
    users: User[];
    groups: Group[];
};

type OnMaskChange = (userType: string, userId: string | null, mask: number) => Promise<void>;
type OnAceDelete = (userType: string, userId: string | null) => Promise<void>;

function AceRowSkeleton() {
    return <tr>
        <td className={'ug'}>
            <Skeleton/>
        </td>
        {Object.keys(aclPermissions).map((k: string) => {
            return <td
                key={k}
                className={'p'}
            >
                <Skeleton
                    variant="rectangular"
                    width={21}
                    height={21}
                    sx={{
                        display: 'inline-block',
                    }}
                />
            </td>
        })}
        <td className={'a'}>
            <Button
                color={'error'}
            >
                <Skeleton width={55}/>
            </Button>
        </td>
    </tr>
}

function getUserName(userType: UserType, userId: string | null, users: User[], groups: Group[]): string | undefined {
    if (userType === UserType.User) {
        if (userId) {
            return users.find(i => i.id === userId)?.username;
        }

        return 'All users';
    } else if (userType === UserType.Group) {
        if (userId) {
            return groups.find(i => i.id === userId)?.name;
        }

        return 'All groups';
    }
}

function AclTable({
                      aces,
                      onMaskChange,
                      onDelete,
    users,
    groups,
                  }: {
    aces: Ace[] | undefined;
    users: User[] | undefined;
    groups: Group[] | undefined;
    onMaskChange: OnMaskChange;
    onDelete: OnAceDelete;
}) {
    const {t} = useTranslation();

    const selectSize = 42;
    const actionsSize = 150;

    return <Box
        component={'table'}
        className={'acl-table'}
        sx={theme => ({
            border: 'none',
            width: '100%',
            borderCollapse: 'separate',
            borderSpacing: 0,
            '.ug, .a': {
                p: 1,
            },
            '.p': {
                width: selectSize,
                maxWidth: selectSize,
                fontWeight: 400,
                textAlign: 'center',
                '&:nth-child(even)': {
                    backgroundColor: theme.palette.grey[100],
                },
            },
            'td': {
                verticalAlign: 'middle',
                '.p': {
                    textAlign: 'center',
                },
            },
            '.a': {
                width: actionsSize,
                maxWidth: actionsSize,
            },
            'th': {
                textAlign: 'left',
                verticalAlign: 'baseline',
                '&.p': {
                    p: 1,
                    'span': {
                        writingMode: 'vertical-rl',
                        margin: '0 auto',
                    }
                },
            },
        })}
    >
        <thead>
        <tr>
            <th
                className={'ug'}
            >
                {t('acl.table.cols.user_group', `User/Group`)}
            </th>
            {Object.keys(aclPermissions).map(k => {
                return <th
                    key={k}
                    className={'p'}
                >
                    <span>{k}</span>
                </th>
            })}
            <th className={'a'}>Actions</th>
        </tr>
        </thead>
        <tbody>
        {!aces && [0, 1, 2].map(k => <AceRowSkeleton key={k}/>)}
        {aces && aces.map((ace) => <AceRow
            onMaskChange={onMaskChange}
            onDelete={onDelete}
            {...ace}
            userName={users && groups ? getUserName(ace.userType, ace.userId, users, groups) : undefined}
            key={ace.id || `${ace.userId}::${ace.userType}`}
        />)}
        </tbody>
    </Box>
}

export default function AclForm({
                                    objectType,
                                    objectId,
                                }: Props) {
    const [data, setData] = useState<State>();
    const {t} = useTranslation();

    useEffect(() => {
        Promise.all([
            getAces(objectType, objectId),
            getUsers(),
            getGroups(),
        ]).then(r => {
            setData({
                aces: r[0],
                users: r[1],
                groups: r[2],
            })
        });
    }, []);

    const addEntry = (entry: { id: string, type: string }, mask: number): void => {
        putAce(entry.type, entry.id, objectType, objectId, mask);

        setData(p => {
            const aces = [...(p!.aces ?? [])];

            aces.push({
                mask: mask,
                userId: entry.id,
                userType: entry.type,
            } as Ace);

            return {
                ...p!,
                aces,
            };
        });
    }

    const onMaskChange: OnMaskChange = useCallback(async (userType: string, userId: string | null, mask: number) => {
        await putAce(userType, userId, objectType, objectId, mask);
    }, [objectType, objectId]);

    const onDelete: OnAceDelete = useCallback(async (userType: string, userId: string | null) => {
        setData(p => {
            return {
                ...p!,
                aces: p!.aces.filter((ace: Ace) => !(ace.userType === userType && ace.userId === userId)),
            };
        });

        await deleteAce(userType, userId, objectType, objectId);
    }, [objectType, objectId]);

    const onSelectUser = (id: string) => {
        addEntry({
            type: 'user',
            id,
        }, 1);
    }

    const onSelectGroup = (id: string) => {
        addEntry({
            type: 'group',
            id,
        }, 1);
    }

    return <div>
        <Grid container spacing={2}
              sx={theme => ({
                  position: 'relative',
                  zIndex: theme.zIndex.tooltip,
              })}
        >
            <Grid item md={6}>
                <FormRow>
                    <GroupSelect
                        data={data?.groups ?? []}
                        placeholder={t('acl.form.user_select.placeholder', `Select group`)}
                        clearOnSelect={true}
                        onChange={(option) => {
                            option && onSelectGroup(option.value);
                        }}
                        disabledValues={data?.aces.filter(ace => ace.userType === 'group' && ace.userId).map(ace => ace.userId!)}
                    />
                </FormRow>
            </Grid>
            <Grid item md={6}>
                <FormRow>
                    <UserSelect
                        data={data?.users ?? []}
                        placeholder={t('acl.form.group_select.placeholder', `Select user`)}
                        clearOnSelect={true}
                        onChange={(option) => {
                            option && onSelectUser(option.value)
                        }}
                        disabledValues={data?.aces.filter(ace => ace.userType === 'user' && ace.userId).map(ace => ace.userId!)}
                    />
                </FormRow>
            </Grid>
        </Grid>
        <AclTable
            aces={data?.aces}
            users={data?.users}
            groups={data?.groups}
            onMaskChange={onMaskChange}
            onDelete={onDelete}
        />
    </div>
}
