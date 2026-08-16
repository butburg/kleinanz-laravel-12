<script lang="ts">
    import AdForm from './AdForm.svelte';

    type Ad = {
        id: string;
        title: string;
        description: string;
        price: number;
        condition: string;
        shipping: string;
        status: string;
        prompt_text?: string | null;
        images: Array<{
            id: number;
            original_name: string;
            url: string;
            is_title: boolean;
            position: number;
            use_cropped?: boolean;
            variants?: {
                large: string;
                large_thumb: string;
                cropped: string | null;
                cropped_thumb: string | null;
            };
            is_cropped?: boolean;
            crop_metadata?: {
                original_size?: [number, number] | null;
                cropped_size?: [number, number] | null;
                cropped_at?: string | null;
            } | null;
        }>;
    };

    interface Props {
        ad: Ad;
        navigation: {
            previousAdId: string | null;
            nextAdId: string | null;
        };
        options: {
            conditions: string[];
            shipping: string[];
            statuses: string[];
            limits: {
                title: number;
                description: number;
                images: number;
                prompt: number;
            };
        };
    }

    let { ad, navigation, options }: Props = $props();
</script>

<AdForm {ad} {navigation} {options} />
