import ActionButton from '@/components/ActionButton';
import BackButton from '@/components/BackButton';
import useForm from '@/hooks/useForm';
import useRoles from '@/hooks/useRoles';
import useAreas from '@/hooks/useAreas';
import { usePage } from '@inertiajs/react';
import ContainerBox from '@/layouts/ContainerBox';
import Layout from '@/layouts/MainLayout';
import { redirectTo } from '@/utils/route';
import { getInitials } from '@/utils/user';

import {
  Anchor,
  Avatar,
  Breadcrumbs,
  Divider,
  FileInput,
  Grid,
  Group,
  MultiSelect,
  PasswordInput,
  Text,
  TextInput,
  Title,
} from '@mantine/core';

const UserEdit = () => {
  const { item, auth } = usePage().props;
  const { getDropdownValues: getAreaDropdownValues } = useAreas();
  const { getDropdownValues } = useRoles();

  const areaOptions = getAreaDropdownValues();
  const isSuperAdmin = auth.user.is_super_admin;
  const manageableAreaIds = areaOptions.map(a => a.value);
  // Áreas del usuario que quien edita no administra: se muestran como info y el backend las conserva
  const foreignAreas = isSuperAdmin
    ? []
    : (item.areas ?? []).filter(a => !manageableAreaIds.includes(String(a.id)));
  const lockAreas = !isSuperAdmin && areaOptions.length <= 1;

  const [form, submit, updateValue] = useForm('post', route('users.update', item.id), {
    _method: 'put',
    avatar: null,
    job_title: item.job_title,
    name: item.name,
    phone: item.phone || '',
    email: item.email,
    password: '',
    password_confirmation: '',
    roles: item.roles,
    area_ids: (item.area_ids ?? [])
      .map(String)
      .filter(id => isSuperAdmin || manageableAreaIds.includes(id)),
  });

  return (
    <>
      <Breadcrumbs
        fz={14}
        mb={30}
      >
        <Anchor
          href='#'
          onClick={() => redirectTo('users.index')}
          fz={14}
        >
          Usuarios
        </Anchor>
        <div>Editar usuario</div>
      </Breadcrumbs>

      <Grid
        justify='space-between'
        align='flex-end'
        gutter='xl'
        mb='lg'
      >
        <Grid.Col span='auto'>
          <Title order={1}>Editar usuario</Title>
        </Grid.Col>
        <Grid.Col span='content'></Grid.Col>
      </Grid>

      <ContainerBox maw={600}>
        <form onSubmit={e => submit(e, { forceFormData: true })}>
          <Grid
            justify='flex-start'
            align='flex-start'
            gutter='lg'
          >
            <Grid.Col span='content'>
              <Avatar
                src={
                  form.data.avatar === null ? item.avatar : URL.createObjectURL(form.data.avatar)
                }
                size={120}
                color='hospitalPrimary'
              >
                {getInitials(form.data.name)}
              </Avatar>
            </Grid.Col>
            <Grid.Col span='auto'>
              <FileInput
                label='Foto de perfil'
                placeholder='Elige una imagen'
                accept='image/png,image/jpeg'
                onChange={image => updateValue('avatar', image)}
                clearable
                error={form.errors.avatar}
              />
              <Text
                size='xs'
                c='dimmed'
                mt='sm'
              >
                Si no eliges una imagen, se generará un avatar automáticamente usando{' '}
                <Anchor
                  href='https://unavatar.io'
                  target='_blank'
                  opacity={0.6}
                >
                  unavatar.io
                </Anchor>{' '}
                service.
              </Text>
            </Grid.Col>
          </Grid>

          <TextInput
            label='Nombre '
            placeholder='Nombre completo del usuario'
            required
            mt='md'
            value={form.data.name}
            onChange={e => updateValue('name', e.target.value)}
            error={form.errors.name}
          />

          <TextInput
            label='Puesto de trabajo'
            placeholder='ej. Desarrollador, Técnico de soporte, etc.'
            required
            mt='md'
            value={form.data.job_title}
            onChange={e => updateValue('job_title', e.target.value)}
            error={form.errors.job_title}
          />

          <MultiSelect
            label='Roles'
            placeholder='Selecciona un rol'
            required
            mt='md'
            value={form.data.roles}
            onChange={values => updateValue('roles', values)}
            data={getDropdownValues()}
            error={form.errors.roles}
          />

          <MultiSelect
            label='Áreas'
            placeholder='Seleccionar áreas'
            required
            mt='md'
            searchable
            value={form.data.area_ids}
            onChange={values => updateValue('area_ids', values)}
            data={areaOptions}
            disabled={lockAreas}
            description={
              foreignAreas.length
                ? `También pertenece a: ${foreignAreas.map(a => a.name).join(', ')} (no se modifican)`
                : undefined
            }
            error={form.errors.area_ids || form.errors['area_ids.0']}
          />

          <TextInput
            label='Teléfono'
            placeholder='Número de teléfono del usuario'
            mt='md'
            value={form.data.phone}
            onChange={e => updateValue('phone', e.target.value)}
            error={form.errors.phone}
          />

          <Divider
            mt='xl'
            mb='md'
            label='Credenciales de acceso'
            labelPosition='center'
          />

          <TextInput
            label='Email'
            placeholder='Correo electrónico del usuario'
            required
            value={form.data.email}
            onChange={e => updateValue('email', e.target.value)}
            onBlur={() => form.validate('email')}
            error={form.errors.email}
          />

          <PasswordInput
            label='Contraseña'
            placeholder='Contraseña del usuario'
            mt='md'
            value={form.data.password}
            onChange={e => updateValue('password', e.target.value)}
            error={form.errors.password}
          />

          <PasswordInput
            label='Confirmar contraseña'
            placeholder='Confirma la contraseña del usuario'
            mt='md'
            value={form.data.password_confirmation}
            onChange={e => updateValue('password_confirmation', e.target.value)}
            error={form.errors.password_confirmation}
          />

          <Group
            justify='space-between'
            mt='xl'
          >
            <BackButton route='users.index' />
            <ActionButton loading={form.processing}>Actualizar</ActionButton>
          </Group>
        </form>
      </ContainerBox>
    </>
  );
};

UserEdit.layout = page => <Layout title='Edit user'>{page}</Layout>;

export default UserEdit;
